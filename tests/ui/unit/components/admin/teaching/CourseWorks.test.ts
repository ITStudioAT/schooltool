import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import CourseWorks from '@/pages/admin/teaching/overview/components/CourseWorks.vue'

describe('CourseWorks defaults', () => {
    it('defaults student sort mode to name', () => {
        const data = (CourseWorks as any).data.call({
            emptyWorkForm: () => ({}),
        })

        expect(data.students_sort_mode).toBe('last_name_first_name')
    })

    it('does not show the bulk action processing state by default', () => {
        const data = (CourseWorks as any).data.call({
            emptyWorkForm: () => ({}),
        })

        expect(data.is_applying_bulk_action).toBe(false)
    })
})

describe('CourseWorks title rendering', () => {
    it('renders work title in second line in Arbeiten list', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="text-caption text-medium-emphasis work-type-first-line"')
        expect(source).toContain('class="text-body-2 work-title-second-line"')
        expect(source).not.toContain('class="text-caption text-medium-emphasis work-title-second-line"')
        expect(source).not.toContain('<span v-if="work.title || work.description">– {{ work.title || work.description }}</span>')
    })

    it('renders grade distribution chips for each work list item', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="work-grade-distribution d-flex flex-wrap ga-1 mt-1"')
        expect(source).toContain('v-for="item in workGradeDistribution(work)"')
        expect(source).toContain('{{ item.grade }}: {{ item.count }}')
    })

    it('adds vertical spacing between work list items', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="work-list-item cursor-pointer"')
        expect(source).toContain('.work-list-item {\n    margin-bottom: 12px;\n}')
        expect(source).toContain('.work-list-item:last-child {\n    margin-bottom: 0;\n}')
    })

    it('renders whether each work list item is group work as a chip beside the work type', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="work-mode-chip ml-1"')
        expect(source).toContain(":color=\"work.is_group_work ? 'primary' : 'default'\"")
        expect(source).toContain("{{ work.is_group_work ? 'Gruppenarbeit' : 'Einzelarbeit' }}")
        expect(source.indexOf('workTypeLabel(work.type)')).toBeLessThan(source.indexOf("work.is_group_work ? 'Gruppenarbeit' : 'Einzelarbeit'"))
        expect(source).not.toContain('<span> - {{ work.is_group_work ? \'Gruppenarbeit\' : \'Einzelarbeit\' }}</span>')
    })

    it('renders grade summaries in the group-work group list', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="group-grade-overview mt-2"')
        expect(source).toContain('Einzelwertung')
        expect(source).toContain('class="group-grade-list"')
        expect(source).toContain('v-for="row in groupStudentGradeRows(group)"')
        expect(source).toContain('{{ row.studentLabel }}')
        expect(source).toContain('{{ row.gradeLabel }}')
        expect(source).toContain('v-if="row.commentPreview"')
        expect(source).toContain('{{ row.commentPreview }}')
        expect(source).toContain('Gruppenwertung')
        expect(source).toContain('Note: {{ sharedGroupGradeLabel(group) }}')
    })

    it('keeps the group comment field available for individual group grading', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("v-model=\"work_form.groups[group_dialog_index].comment\"")
        expect(source).toContain(":label=\"work_form.groups[group_dialog_index].use_individual_grades ? 'Kommentar (Gruppe)' : 'Kommentar (für alle)'\"")
        expect(source).not.toContain('v-if="!work_form.groups[group_dialog_index].use_individual_grades"\n                                            v-model="work_form.groups[group_dialog_index].comment"')
        expect(source).toContain("comment: group.comment ?? ''")
    })
})

describe('CourseWorks course-specific schema', () => {
    it('prefers the selected course schema snapshot over the global teaching store', () => {
        const computed = (CourseWorks as any).computed
        const ctx: Record<string, unknown> = {
            selected_course: {
                teaching_schema_id: 'schema-teacher',
                teacher_teaching_schema: {
                    id: 'schema-teacher',
                    works: [{ short_name: 'MA', name: 'Mitarbeit' }],
                    grading: { semester_count: 2 },
                },
            },
            teachingStore: {
                schemaById: () => ({ id: 'schema-global', works: [{ short_name: 'AK', name: 'Auftrag' }], grading: { semester_count: 1 } }),
            },
        }

        ctx.selectedCourseSchema = computed.selectedCourseSchema.call(ctx)

        expect((ctx.selectedCourseSchema as any).id).toBe('schema-teacher')
        expect(computed.teachingWorks.call(ctx)).toEqual([{ short_name: 'MA', name: 'Mitarbeit' }])
        expect(computed.semesterCount.call(ctx)).toBe(2)
    })
})

describe('CourseWorks points mode', () => {
    it('shows the Punkte action only for non-group work types with points-note configuration', () => {
        const computed = (CourseWorks as any).computed
        const ctx: Record<string, unknown> = {
            work_form: { type: 'SA', is_group_work: false },
            teachingWorks: [
                {
                    short_name: 'SA',
                    name: 'Schularbeit',
                    points_note_enabled: true,
                    points_table: [{ grade: '1', min_points: 40 }],
                    points_sonst_grade: '5',
                },
            ],
            workConfigForType(type: string) {
                return (this.teachingWorks as Array<Record<string, unknown>>).find((work) => work.short_name === type) ?? null
            },
            workSupportsPoints: (work: Record<string, unknown>) => Boolean(work?.points_note_enabled),
        }

        ctx.selectedTypeWork = computed.selectedTypeWork.call(ctx)

        expect(computed.selectedTypeSupportsPoints.call(ctx)).toBe(true)
        expect(computed.selectedTypeSupportsPoints.call({
            ...ctx,
            work_form: { type: 'SA', is_group_work: true },
        })).toBe(false)
    })

    it('derives the grade from entered points for per-work points tables', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            work_form: {
                groups: [{
                    student_ids: [11],
                    grades: {},
                    comments: {},
                    points: {},
                }],
            },
            selectedTypeWork: {
                short_name: 'SA',
                points_note_enabled: true,
                points_table: [
                    { grade: '1', min_points: 40 },
                    { grade: '2', min_points: 35 },
                    { grade: '3', min_points: 30 },
                    { grade: '4', min_points: 25 },
                ],
                points_sonst_grade: '5',
            },
            normalizeNumericInput: methods.normalizeNumericInput,
            normalizePointsNumber: methods.normalizePointsNumber,
            workSupportsPoints: methods.workSupportsPoints,
            gradeFromPointsForWork: methods.gradeFromPointsForWork,
        }

        methods.setStudentPoints.call(ctx, 0, 11, '37,5')

        expect(ctx.work_form.groups[0].points[11]).toBe('37,5')
        expect(ctx.work_form.groups[0].grades[11]).toBe('2')
    })

    it('does not enable per-work point entry from semester point thresholds alone', () => {
        const methods = (CourseWorks as any).methods

        expect(methods.workSupportsPoints.call({}, {
            points_note_enabled: false,
            semester_points_table: [{ grade: '1', min_points: 5 }],
            semester_points_sonst_grade: '5',
        })).toBe(false)
    })

    it('serializes numeric points with student ids for saving', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            normalizeNumericInput: methods.normalizeNumericInput,
            normalizePointsNumber: methods.normalizePointsNumber,
        }

        const result = methods.serializeGroupPoints.call(ctx, {
            student_ids: [11, 12, 13],
            points: {
                11: '42',
                12: '37,5',
                13: '',
            },
        })

        expect(result).toEqual([
            { student_id: 11, points: 42 },
            { student_id: 12, points: 37.5 },
        ])
    })

    it('renders the group dialog grade picker as clickable chips', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="d-flex flex-wrap ga-1 group-dialog-grade-chips"')
        expect(source).toContain('@click="setGroupGrade(group_dialog_index, grade.value)"')
        expect(source).not.toContain('v-model="work_form.groups[group_dialog_index].grade"')
    })

    it('renders group dialog students as removable chips with an inline add area toggle', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain(":icon=\"group_dialog_add_students_open ? 'mdi-close' : 'mdi-plus'\"")
        expect(source).toContain('v-if="group_dialog_add_students_open" class="group-dialog-add-students"')
        expect(source).toContain('@click="addStudentToGroup(group_dialog_index, student.value)"')
        expect(source).toContain('@click:close="removeStudentFromGroup(group_dialog_index, studentId)"')
        expect(source).not.toContain('<v-menu>')
    })

    it('updates the shared group grade when a chip is selected', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            work_form: {
                groups: [{ grade: '3' }],
            },
        }

        methods.setGroupGrade.call(ctx, 0, '1')
        expect(ctx.work_form.groups[0].grade).toBe('1')

        methods.setGroupGrade.call(ctx, 0, '')
        expect(ctx.work_form.groups[0].grade).toBe('')
    })

    it('adds and removes students in the group dialog through helper methods', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            work_form: {
                groups: [{ student_ids: [3] }],
            },
            group_dialog_add_students_open: true,
            availableStudentItems(groupIndex: number) {
                expect(groupIndex).toBe(0)

                const items = [
                    { value: 2, title: '1A Beta, Bea' },
                    { value: 3, title: '1A Alpha, Ada' },
                ]

                const selectedIds = new Set((this.work_form.groups[groupIndex].student_ids || []).map(String))

                return items.filter((item) => !selectedIds.has(String(item.value)))
            },
            updateGroupStudents(group: Record<string, any>, ids: number[]) {
                group.student_ids = [...ids].sort((a, b) => a - b)
            },
        }

        methods.addStudentToGroup.call(ctx, 0, 2)
        expect(ctx.work_form.groups[0].student_ids).toEqual([2, 3])

        methods.addStudentToGroup.call(ctx, 0, 5)
        expect(ctx.work_form.groups[0].student_ids).toEqual([2, 3])
        expect(ctx.group_dialog_add_students_open).toBe(false)

        methods.removeStudentFromGroup.call(ctx, 0, 3)
        expect(ctx.work_form.groups[0].student_ids).toEqual([2])
    })

    it('does not list students that are already in the current group as available', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            work_form: {
                groups: [
                    { student_ids: [3] },
                    { student_ids: [9] },
                ],
            },
            studentItems: [
                { value: 2, title: '1A Beta, Bea' },
                { value: 3, title: '1A Alpha, Ada' },
                { value: 9, title: '1A Delta, Dan' },
            ],
        }

        expect(methods.availableStudentItems.call(ctx, 0)).toEqual([
            { value: 2, title: '1A Beta, Bea' },
        ])
    })

    it('toggles the inline add-students area in the group dialog', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            group_dialog_add_students_open: false,
        }

        methods.toggleGroupStudentPicker.call(ctx)
        expect(ctx.group_dialog_add_students_open).toBe(true)

        methods.toggleGroupStudentPicker.call(ctx)
        expect(ctx.group_dialog_add_students_open).toBe(false)
    })

    it('shows processing before applying a bulk grade', async () => {
        const methods = (CourseWorks as any).methods
        let sawProcessingState = false
        const ctx: Record<string, any> = {
            is_applying_bulk_action: false,
            selected_student_ids: [11],
            bulk_grade: '1',
            bulk_comment: '',
            work_form: {
                groups: [
                    {
                        student_ids: [11, 12],
                        grades: {},
                        comments: {},
                    },
                ],
            },
            async waitForBulkActionPaint() {
                sawProcessingState = this.is_applying_bulk_action
            },
        }

        await methods.applyBulkAction.call(ctx)

        expect(sawProcessingState).toBe(true)
        expect(ctx.work_form.groups[0].grades).toEqual({ 11: '1' })
        expect(ctx.work_form.groups[0].comments).toEqual({})
        expect(ctx.bulk_grade).toBeNull()
        expect(ctx.selected_student_ids).toEqual([])
        expect(ctx.is_applying_bulk_action).toBe(false)
    })
})

describe('CourseWorks grade distribution', () => {
    it('builds per-student group grade rows for individual group grading', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            work_form: { type: 'PR' },
            activeCourseStudents: [
                { id: 2, schoolclass: '1A', last_name: 'Beta', first_name: 'Bea' },
                { id: 1, schoolclass: '1A', last_name: 'Alpha', first_name: 'Ada' },
            ],
            teachingWorks: [
                {
                    short_name: 'PR',
                    grades: [
                        { grade: '1' },
                        { grade: '2' },
                    ],
                },
            ],
        }
        Object.assign(ctx, methods)

        const result = methods.groupStudentGradeRows.call(ctx, {
            student_ids: [2, 1],
            use_individual_grades: true,
            grades: {
                1: '1',
                2: '',
            },
            comments: {
                1: 'Sehr gute Mitarbeit',
                2: '',
            },
        })

        expect(result).toEqual([
            {
                studentId: 1,
                studentLabel: '1A Alpha, Ada',
                gradeValue: '1',
                gradeLabel: '1',
                commentValue: 'Sehr gute Mitarbeit',
                commentPreview: 'Sehr gute Mitarbeit',
            },
            {
                studentId: 2,
                studentLabel: '1A Beta, Bea',
                gradeValue: '',
                gradeLabel: 'Keine Note',
                commentValue: '',
                commentPreview: '',
            },
        ])
    })

    it('builds a shared group grade label for group grading', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            work_form: { type: 'PR' },
            teachingWorks: [
                {
                    short_name: 'PR',
                    grades: [
                        { grade: '1' },
                        { grade: '2' },
                    ],
                },
            ],
        }
        Object.assign(ctx, methods)

        expect(methods.sharedGroupGradeLabel.call(ctx, { grade: '2' })).toBe('2')
        expect(methods.sharedGroupGradeLabel.call(ctx, { grade: '' })).toBe('Keine Note')
    })

    it('counts individual grades and missing grades for a work item', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            activeCourseStudents: [
                { id: 1 },
                { id: 2 },
                { id: 3 },
                { id: 4 },
            ],
            teachingWorks: [
                {
                    short_name: 'SA',
                    grades: [
                        { grade: '1' },
                        { grade: '2' },
                        { grade: '3' },
                    ],
                },
            ],
        }
        Object.assign(ctx, methods)

        const result = methods.workGradeDistribution.call(ctx, {
            id: 10,
            type: 'SA',
            is_group_work: false,
            groups: [
                { student_ids: [1], grades: { 1: '1' } },
                { student_ids: [2], grades: { 2: '2' } },
                { student_ids: [3], grades: { 3: '2' } },
                { student_ids: [4], grades: { 4: '' } },
            ],
        })

        expect(result).toEqual([
            { grade: '1', count: 1, color: 'primary' },
            { grade: '2', count: 2, color: 'primary' },
            { grade: 'Offen', count: 1, color: 'warning' },
        ])
    })

    it('counts a shared group grade for every student in the group', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            activeCourseStudents: [
                { id: 1 },
                { id: 2 },
                { id: 3 },
                { id: 4 },
                { id: 5 },
            ],
            teachingWorks: [
                {
                    short_name: 'PR',
                    grades: [
                        { grade: '1' },
                        { grade: '2' },
                    ],
                },
            ],
        }
        Object.assign(ctx, methods)

        const result = methods.workGradeDistribution.call(ctx, {
            id: 11,
            type: 'PR',
            is_group_work: true,
            groups: [
                { student_ids: [1, 2, 3], grade: '1', grades: {} },
                { student_ids: [4, 5], grade: '2', grades: {} },
            ],
        })

        expect(result).toEqual([
            { grade: '1', count: 3, color: 'primary' },
            { grade: '2', count: 2, color: 'primary' },
        ])
    })

    it('does not count canceled students in the distribution', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            activeCourseStudents: [
                { id: 1 },
                { id: 2 },
            ],
            teachingWorks: [
                {
                    short_name: 'SA',
                    grades: [
                        { grade: '1' },
                        { grade: '5' },
                    ],
                },
            ],
        }
        Object.assign(ctx, methods)

        const result = methods.workGradeDistribution.call(ctx, {
            id: 12,
            type: 'SA',
            is_group_work: false,
            groups: [
                { student_ids: [1], grades: { 1: '1' } },
                { student_ids: [2], grades: { 2: '1' } },
                { student_ids: [3], grades: { 3: '5' } },
            ],
        })

        expect(result).toEqual([
            { grade: '1', count: 2, color: 'primary' },
        ])
    })
})
