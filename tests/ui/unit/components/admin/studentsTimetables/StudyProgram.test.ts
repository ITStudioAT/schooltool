import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import SubjectsOverview from '@/pages/admin/studentsTimetables/subjectsOverview/SubjectsOverview.vue'
import Timetable from '@/pages/admin/studentsTimetables/timetable/Timetable.vue'

describe('Students timetable study programs', () => {
    it('redirects removed subject-import routes to the remaining imports overview', () => {
        const methods = (Timetable as any).methods
        const replace = vi.fn()
        const ctx: any = {
            $route: {
                params: {
                    subsection: 'imports',
                    detail: 'faecher',
                },
            },
            $router: { replace },
            importPage: 'faecher',
            importSubPage: 'import',
        }

        expect(methods.normalizedImportPage.call({ canManageTimetableImports: true }, 'faecher')).toBe('')
        expect(methods.redirectRemovedSubjectImportRoute.call(ctx)).toBe(true)

        expect(ctx.importPage).toBe('')
        expect(ctx.importSubPage).toBe('')
        expect(replace).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable/imports',
        })
    })

    it('shows eight normal semesters and five compact semesters', () => {
        const computed = (SubjectsOverview as any).computed

        const normalSemesters = computed.subjectOverviewSemesters.call({
            studyProgram: 'normalstudium',
            activeSubjectRows: [],
        })
        const compactSemesters = computed.subjectOverviewSemesters.call({
            studyProgram: 'kompaktstudium',
            activeSubjectRows: [],
        })

        expect(normalSemesters).toEqual([1, 2, 3, 4, 5, 6, 7, 8])
        expect(compactSemesters).toEqual([1, 2, 3, 4, 5])
    })

    it('groups separate compact religion alternatives per module', () => {
        const methods = (SubjectsOverview as any).methods
        const ctx: any = {
            ...methods,
            studyProgram: 'kompaktstudium',
            activeSubjectRows: [
                { semester: 5, branch: null, json_code: 'R3', json_subject: 'R', hours_per_week: 1 },
                { semester: 5, branch: null, json_code: 'ET3', json_subject: 'ET', hours_per_week: 1 },
                { semester: 5, branch: null, json_code: 'R4', json_subject: 'R', hours_per_week: 1 },
                { semester: 5, branch: null, json_code: 'ET4', json_subject: 'ET', hours_per_week: 1 },
            ],
        }

        const groups = methods.subjectOverviewReligionChoiceGroups.call(ctx)
        const courses = methods.subjectOverviewCourseItems.call(ctx, ctx.activeSubjectRows)

        expect(groups).toHaveLength(2)
        expect(groups.map((group: any) => group.codes)).toEqual([
            ['R3', 'ET3'],
            ['R4', 'ET4'],
        ])
        expect(courses).toMatchObject([
            { display_code: 'R3+4/ET3+4*', hours_per_week: 2 },
        ])
        expect(methods.sumSubjectHours.call(ctx, ctx.activeSubjectRows)).toBe(2)
    })

    it('combines split compact modules and keeps branch-specific art choices', () => {
        const methods = (SubjectsOverview as any).methods
        const moduleContext: any = {
            ...methods,
            studyProgram: 'kompaktstudium',
            activeSubjectRows: [],
        }
        const compactCourses = methods.subjectOverviewCourseItems.call(moduleContext, [
            { semester: 1, json_code: 'D2', json_subject: 'D', hours_per_week: 1.5 },
            { semester: 1, json_code: 'D3', json_subject: 'D', hours_per_week: 1.5 },
        ])
        const artContext: any = {
            ...methods,
            studyProgram: 'kompaktstudium',
            activeSubjectRows: [
                { semester: 4, branch: 'wirtschaftskundlich', json_code: 'BE1', json_subject: 'BE' },
                { semester: 4, branch: 'wirtschaftskundlich', json_code: 'ME1', json_subject: 'ME' },
                { semester: 4, branch: 'gymnasial', json_code: 'BE1', json_subject: 'BE' },
                { semester: 4, branch: 'gymnasial', json_code: 'ME1', json_subject: 'ME' },
                { semester: 5, branch: 'gymnasial', json_code: 'BE2', json_subject: 'BE' },
                { semester: 5, branch: 'gymnasial', json_code: 'ME2', json_subject: 'ME' },
            ],
        }

        expect(compactCourses).toMatchObject([
            { display_code: 'D2+3', hours_per_week: 3 },
        ])
        expect(methods.subjectOverviewArtChoiceGroups.call(artContext)).toMatchObject([
            { semester: 4, branch: 'wirtschaftskundlich' },
            { semester: 5, branch: 'gymnasial' },
        ])
    })

    it('counts compact language paths per module and combines them for display', () => {
        const methods = (SubjectsOverview as any).methods
        const languageRows = [
            { semester: 4, branch: null, json_code: 'L4', json_subject: 'L', hours_per_week: 2 },
            { semester: 4, branch: null, json_code: 'F4', json_subject: 'F', hours_per_week: 2 },
            { semester: 4, branch: null, json_code: 'S4', json_subject: 'S', hours_per_week: 2 },
            { semester: 4, branch: null, json_code: 'L5', json_subject: 'L', hours_per_week: 2 },
            { semester: 4, branch: null, json_code: 'F5', json_subject: 'F', hours_per_week: 2 },
            { semester: 4, branch: null, json_code: 'S5', json_subject: 'S', hours_per_week: 2 },
        ]
        const ctx: any = {
            ...methods,
            studyProgram: 'kompaktstudium',
            activeSubjectRows: languageRows,
        }

        expect(methods.subjectOverviewCourseItems.call(ctx, languageRows)).toMatchObject([
            { display_code: 'L4+5/F4+5/S4+5*', hours_per_week: 4 },
        ])
        expect(methods.sumSubjectHours.call(ctx, languageRows)).toBe(4)
    })

    it('renders the study-program toggle only with the subject views', () => {
        const timetableSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetable/Timetable.vue',
            'utf8',
        )
        const subjectsSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/subjectsOverview/SubjectsOverview.vue',
            'utf8',
        )

        expect(timetableSource).not.toContain('subjectImportPrompt')
        expect(timetableSource).not.toContain("key: 'faecher'")
        expect(subjectsSource).toContain("{ value: NORMAL_STUDY_PROGRAM, label: 'Normalstudium' }")
        expect(subjectsSource).toContain("{ value: COMPACT_STUDY_PROGRAM, label: 'Kompaktstudium' }")
        expect(subjectsSource).toContain(':disabled="studyProgramSwitchDisabled"')
        expect(subjectsSource).toContain('Schul-Unterrichtseinheiten')
        expect(subjectsSource).not.toContain('<FileUpload')
        expect(subjectsSource).not.toContain('loadImports()')
    })
})
