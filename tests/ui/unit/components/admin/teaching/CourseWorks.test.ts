import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import CourseWorks from '@/pages/admin/teaching/overview/components/CourseWorks.vue'

describe('CourseWorks defaults', () => {
    it('uses the current grading types and requires the enabled maximum plus value', () => {
        const computed = (CourseWorks as any).computed
        const definition = { category: 'Benotung', short_name: 'A', properties_mode: 'plus', allows_maximum_plus: true }
        const ctx = {
            selected_course: { teaching_entry_area: { id: 1, entry_definitions: [definition, { category: 'Verhalten' }] } },
            selectedCourseSchema: { works: [{ short_name: 'OLD' }] },
            work_form: { type: 'A', maximum_plus: null as string | null },
        }

        expect(computed.teachingWorks.call(ctx)).toEqual([definition])
        expect(computed.workRequiresMaximumPlus.call(ctx)).toBe(true)
        expect(computed.workMaximumError.call(ctx)).toContain('positive ganze Zahl')
        ctx.work_form.maximum_plus = '6'
        expect(computed.workMaximumError.call(ctx)).toBe('')
        definition.allows_maximum_plus = false
        expect(computed.workRequiresMaximumPlus.call(ctx)).toBe(false)
    })

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

    it('does not start in a group-work mode transition', () => {
        const data = (CourseWorks as any).data.call({
            emptyWorkForm: () => ({}),
        })

        expect(data.is_changing_group_work_mode).toBe(false)
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
        expect(source).toContain('class="work-meta-row"')
        expect(source).toContain('class="text-body-2 work-title-second-line"')
        expect(source).toContain('v-if="work.description" class="text-caption work-description-line"')
        expect(source).toContain('{{ work.title || \'—\' }}')
        expect(source).toContain('{{ work.description }}')
        expect(source).not.toContain('{{ work.description || \'—\' }}')
        expect(source).not.toContain('class="text-caption text-medium-emphasis work-title-second-line"')
        expect(source).not.toContain('<span v-if="work.title || work.description">– {{ work.title || work.description }}</span>')
    })

    it('renders grade distribution chips for each work list item', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="work-bottom-row"')
        expect(source).toContain('class="work-grade-distribution d-flex flex-wrap ga-1"')
        expect(source).toContain('v-for="item in workGradeDistribution(work)"')
        expect(source).toContain('{{ item.grade }}: {{ item.count }}')
        expect(source).toContain('class="work-actions d-flex align-center ga-1"')
    })

    it('renders and edits the finish-until date', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const watcher = (CourseWorks as any).watch['work_form.finish_until_date']
        const ctx = {
            work_form: {
                finish_until_date: new Date(2026, 4, 20),
            },
            toDateString: (date: Date) => [
                date.getFullYear(),
                String(date.getMonth() + 1).padStart(2, '0'),
                String(date.getDate()).padStart(2, '0'),
            ].join('-'),
        }

        watcher.call(ctx, ctx.work_form.finish_until_date)

        expect(source).toContain('v-model="work_form.finish_until_date" clearable label="Fertig bis"')
        expect(source).toContain('Fertig bis {{ formatDate(work.finish_until_date) }}')
        expect(ctx.work_form.finish_until_date).toBe('2026-05-20')
    })

    it('defaults the finish-until date when the work date is first selected', () => {
        const watcher = (CourseWorks as any).watch['work_form.date_for_all_groups']
        const ctx = {
            is_initializing_form: false,
            normalizeDateString: (date: string) => date,
            work_form: {
                date_for_all_groups: '2026-05-16',
                finish_until_date: '',
                groups: [],
            },
        }

        watcher.call(ctx, ctx.work_form.date_for_all_groups)

        expect(ctx.work_form.finish_until_date).toBe('2026-05-16')
    })

    it('preserves a manually selected finish-until date when the work date changes', () => {
        const watcher = (CourseWorks as any).watch['work_form.date_for_all_groups']
        const ctx = {
            is_initializing_form: false,
            normalizeDateString: (date: string) => date,
            work_form: {
                date_for_all_groups: '2026-05-16',
                finish_until_date: '2026-05-20',
                groups: [],
            },
        }

        watcher.call(ctx, ctx.work_form.date_for_all_groups)

        expect(ctx.work_form.finish_until_date).toBe('2026-05-20')
    })

    it('opens a specific work from the route query after jumping from dates', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const methods = (CourseWorks as any).methods
        const editWork = vi.fn()
        const ctx: Record<string, any> = {
            courseWorks: [
                { id: 11, teaching_course_id: 20, title: 'Andere Arbeit' },
                { id: 23, teaching_course_id: 20, title: 'Excel' },
            ],
            selected_course: { id: 20 },
            selected_courseWork: null,
            editWork,
            $route: { query: { work: '23' } },
        }

        const opened = methods.openWorkFromRouteQuery.call(ctx)

        expect(source).toContain('this.openWorkFromRouteQuery()')
        expect(source).toContain("'$route.query.work'()")
        expect(opened).toBe(true)
        expect(ctx.selected_courseWork).toEqual({ id: 23, teaching_course_id: 20, title: 'Excel' })
        expect(editWork).toHaveBeenCalledWith({ id: 23, teaching_course_id: 20, title: 'Excel' })
    })

    it('returns to the dates panel after cancelling a work opened from dates', () => {
        const methods = (CourseWorks as any).methods
        const replace = vi.fn(() => Promise.resolve())
        const ctx: Record<string, any> = {
            action: 'edit_course_work',
            selected_courseWork: { id: 23, title: 'Excel' },
            work_form: { id: 23 },
            pending_group_work: true,
            pending_random_groups: true,
            show_bulk_action: true,
            bulk_grade: '1',
            bulk_comment: 'Kommentar',
            selected_student_ids: [1, 2],
            show_points_grading_view: true,
            closeCommentDialog: vi.fn(),
            details_editable: true,
            details_snapshot: { id: 23 },
            show_dates: false,
            show_works: true,
            courseStore: {
                previous_selected_student: null,
                previous_show_infos: null,
                previous_show_dates: null,
            },
            shouldReturnToDatesPanel: methods.shouldReturnToDatesPanel,
            returnToDatesPanel: methods.returnToDatesPanel,
            emptyWorkForm: () => ({ groups: [] }),
            $route: {
                query: {
                    course: '20',
                    date: '370',
                    grades: 'sem1,sem2,year',
                    panel: 'works',
                    work: '23',
                    return_panel: 'dates',
                },
            },
            $router: { replace },
        }

        methods.abortEdit.call(ctx)

        expect(ctx.action).toBe('')
        expect(ctx.selected_courseWork).toBeNull()
        expect(ctx.work_form).toEqual({ groups: [] })
        expect(ctx.show_dates).toBe(true)
        expect(ctx.show_works).toBe(false)
        expect(replace).toHaveBeenCalledWith({
            query: {
                course: '20',
                date: '370',
                grades: 'sem1,sem2,year',
                panel: 'dates',
            },
        })
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

    it('renders the Arbeiten list as a responsive two-column grid', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<v-list density="compact" class="work-list-grid">')
        expect(source).toContain('.work-list-grid {')
        expect(source).toContain('display: grid;')
        expect(source).toContain('@media (min-width: 900px)')
        expect(source).toContain('grid-template-columns: repeat(2, minmax(0, 1fr));')
        expect(source).toContain('.work-list-grid .work-list-item {')
        expect(source).toContain('align-self: stretch;')
        expect(source).toContain('.work-list-grid .work-list-item :deep(.v-list-item__content)')
    })

    it('renders work entries with type-based background colors', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const methods = (CourseWorks as any).methods
        const firstTypeClass = methods.workEntryBackgroundClass.call({}, { type: 'SA' })
        const sameTypeClass = methods.workEntryBackgroundClass.call({}, { type: 'SA' })
        const emptyTypeClass = methods.workEntryBackgroundClass.call({}, { type: '' })

        expect(source).toContain('class="work-row w-100" :class="workEntryBackgroundClass(work)"')
        expect(source).toContain('.work-row--type-1 {')
        expect(source).toContain('border-radius: 8px;')
        expect(source).toContain('padding: 8px;')
        expect(firstTypeClass).toBe(sameTypeClass)
        expect(firstTypeClass).toMatch(/^work-row--type-[1-6]$/)
        expect(emptyTypeClass).toBe('work-row--type-empty')
    })

    it('renders work detail students as two-column colored blocks', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const methods = (CourseWorks as any).methods
        const firstStudentClass = methods.studentBlockBackgroundClass.call({}, 11)
        const otherStudentClass = methods.studentBlockBackgroundClass.call({}, 12)
        const emptyStudentClass = methods.studentBlockBackgroundClass.call({}, null)

        expect(source).toContain('class="mt-3 student-card-grid"')
        expect(source).toContain('class="pa-3 student-card-block"')
        expect(source).toContain('class="pa-2 student-card-block"')
        expect(source).toContain(':class="studentBlockBackgroundClass(row.studentId)"')
        expect(source).toContain('<div v-else class="mt-3 student-card-grid">')
        expect(source).not.toContain('toggleChipGradingView')
        expect(source).not.toContain('Chip-Ansicht')
        expect(source).not.toContain('student-panel-grid')
        expect(source).not.toContain('v-expansion-panels v-else')
        expect(source).toContain('grid-template-columns: repeat(2, minmax(0, 1fr));')
        expect(source).toContain('.student-card-block--student {')
        expect(firstStudentClass).toBe(otherStudentClass)
        expect(firstStudentClass).toBe('student-card-block--student')
        expect(emptyStudentClass).toBe('student-card-block--type-empty')
    })

    it('preserves line breaks in student comment previews', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="text-caption text-medium-emphasis mt-1 student-comment-preview"')
        expect(source).toContain('const commentPreview = (commentValue || \'\').toString().trim()')
        expect(source).not.toContain(".trim().slice(0, 120)")
        expect(source).toContain('.student-comment-preview {')
        expect(source).toContain('white-space: pre-wrap;')
        expect(source).toContain('overflow-wrap: anywhere;')
    })

    it('keeps the full new work form editable from the beginning', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const methods = (CourseWorks as any).methods
        const state = {
            emptyWorkForm: methods.emptyWorkForm,
            buildIndividualGroups: () => [],
            selected_course: { id: 20 },
            closeCommentDialog: vi.fn(),
            $nextTick: vi.fn(),
        }

        methods.newWork.call(state)

        expect(source).toContain(':disabled="is_saving || isEditingExistingDetails"')
        expect(source).toContain(':style="isEditingExistingDetails ? \'pointer-events:none; opacity:0.45\' : \'\'"')
        expect(source).toContain('<template v-if="action === \'new_course_work\' || details_editable">')
        expect(source).toContain('isEditingExistingDetails()')
        expect(state.details_editable).toBe(false)
        expect(state.action).toBe('new_course_work')
    })

    it('prevents saving work without a selected type', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const computed = (CourseWorks as any).computed

        expect(source).not.toContain('v-if="!hasSelectedWorkType"')
        expect(source).toContain(':disabled="!canSaveWork"')
        expect(source).toContain('if (!this.hasSelectedWorkType) {')
        expect(source).toContain('useNotificationStore().notify({')
        expect(source).toContain("type: 'warning'")
        expect(computed.hasSelectedWorkType.call({ selectedTypeWork: null })).toBe(false)
        expect(computed.canSaveWork.call({
            is_saving: false,
            isEditingExistingDetails: false,
            hasSelectedWorkType: false,
        })).toBe(false)
    })

    it('keeps group size available immediately for group work', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('label="Gruppengröße"')
        expect(source).toContain('v-model.number="work_form.group_size"')
        expect(source).not.toContain('<v-text-field\n                                            v-if="work_form.is_random_groups"')
        expect(source).not.toContain('v-if="work_form.is_random_groups && hasUnassignedStudents"')
        expect(source).toContain("{{ work_form.groups?.length ? 'Neu erstellen' : 'Erstellen' }}")
    })

    it('can regenerate random groups when all students are already assigned', () => {
        const methods = (CourseWorks as any).methods
        const state = {
            activeCourseStudents: [
                { id: 1 },
                { id: 2 },
                { id: 3 },
                { id: 4 },
            ],
            work_form: {
                date_for_all_groups: '2026-05-16',
                group_size: 2,
                groups: [
                    { student_ids: [1, 2] },
                    { student_ids: [3, 4] },
                ],
            },
        }

        methods.generateRandomGroups.call(state)

        expect(state.work_form.groups).toHaveLength(2)
        expect(state.work_form.groups.flatMap((group) => group.student_ids).sort()).toEqual(['1', '2', '3', '4'])
    })

    it('locks the group-work switch once a work exists', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const computed = (CourseWorks as any).computed

        expect(source).toContain('v-if="!canChangeGroupWorkMode"')
        expect(source).toContain('<v-switch\n                                    v-else')
        expect(source).toContain('if (!this.canChangeGroupWorkMode) return')
        expect(computed.canChangeGroupWorkMode.call({
            action: 'new_course_work',
            work_form: { id: null },
        })).toBe(true)
        expect(computed.canChangeGroupWorkMode.call({
            action: 'edit_course_work',
            work_form: { id: 10 },
        })).toBe(false)
        expect(computed.canChangeGroupWorkMode.call({
            action: 'new_course_work',
            work_form: { id: 10 },
        })).toBe(false)
    })

    it('ignores group-work switch changes for saved works', () => {
        const methods = (CourseWorks as any).methods
        const state = {
            work_form: {
                id: 10,
                is_group_work: false,
                is_random_groups: false,
                group_size: null,
                groups: [],
            },
            canChangeGroupWorkMode: false,
            pending_group_work: null,
            group_work_switch_key: 0,
            toBoolean: methods.toBoolean,
        }

        methods.setGroupWork.call(state, true)

        expect(state.work_form.is_group_work).toBe(false)
        expect(state.pending_group_work).toBeNull()
        expect(state.group_work_switch_key).toBe(0)
    })

    it('allows group-work switch changes while creating a new work', () => {
        const methods = (CourseWorks as any).methods
        const state = {
            work_form: {
                id: null,
                is_group_work: false,
                is_random_groups: false,
                group_size: null,
                groups: [],
            },
            canChangeGroupWorkMode: true,
            pending_group_work: null,
            group_work_switch_key: 0,
            is_changing_group_work_mode: false,
            toBoolean: methods.toBoolean,
            hasIndividualEntries: methods.hasIndividualEntries,
            hasGroupEntries: methods.hasGroupEntries,
            applyGroupWorkMode: methods.applyGroupWorkMode,
            syncGroupWorkModeState: methods.syncGroupWorkModeState,
            buildIndividualGroups: vi.fn(() => []),
            generateRandomGroups: vi.fn(),
        }

        methods.setGroupWork.call(state, true)

        expect(state.pending_group_work).toBeNull()
        expect(state.work_form.is_group_work).toBe(true)
        expect(state.work_form.group_size).toBe(2)
        expect(state.work_form.groups).toEqual([])
    })

    it('shows the current work mode clearly in the edit form', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const computed = (CourseWorks as any).computed

        expect(source).toContain('class="work-mode-chip"')
        expect(source).toContain('v-if="!canChangeGroupWorkMode"')
        expect(source).toContain('{{ workModeLabel }}')
        expect(computed.workModeLabel.call({ work_form: { is_group_work: true } })).toBe('Gruppenarbeit')
        expect(computed.workModeLabel.call({ work_form: { is_group_work: false } })).toBe('Einzelarbeit')
        expect(computed.workModeIcon.call({ work_form: { is_group_work: true } })).toBe('mdi-account-group')
        expect(computed.workModeIcon.call({ work_form: { is_group_work: false } })).toBe('mdi-account')
    })

    it('does not treat empty serialized student rows as filled entries', () => {
        const methods = (CourseWorks as any).methods

        expect(methods.hasFilledStudentValues([{ student_id: 1, grade: '' }], 'grade')).toBe(false)
        expect(methods.hasFilledStudentValues([{ student_id: 1, grade: '3' }], 'grade')).toBe(true)
    })

    it('renders whether each work list item is group work in the type row', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('{{ workTypeModeLabel(work) }}')
        expect(source).toContain('workTypeModeLabel(work) {')
        expect(source).toContain("const modeLabel = work?.is_group_work ? 'Gruppenarbeit' : 'Einzelarbeit'")
        expect(source).toContain('.work-meta-row {')
        expect(source).toContain('justify-content: space-between;')
        expect(source).not.toContain('class="work-mode-chip ml-1"')
        expect(source).not.toContain('<span> - {{ work.is_group_work ? \'Gruppenarbeit\' : \'Einzelarbeit\' }}</span>')
    })

    it('shows an exclamation marker for group works with unassigned students', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseWorks.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const methods = (CourseWorks as any).methods
        const ctx = {
            activeCourseStudents: [
                { id: 1 },
                { id: 2 },
                { id: 3 },
            ],
        }

        expect(source).toContain('v-if="workHasUnassignedStudents(work)"')
        expect(source).toContain('class="work-unassigned-chip"')
        expect(source).toContain('Nicht alle Schüler:innen sind einer Gruppe zugeordnet')
        expect(methods.workHasUnassignedStudents.call(ctx, {
            is_group_work: true,
            groups: [{ student_ids: [1, 2] }],
        })).toBe(true)
        expect(methods.workHasUnassignedStudents.call(ctx, {
            is_group_work: true,
            groups: [{ student_ids: [1, 2, 3] }],
        })).toBe(false)
        expect(methods.workHasUnassignedStudents.call(ctx, {
            is_group_work: false,
            groups: [{ student_ids: [1] }],
        })).toBe(false)
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

    it('opens the new group dialog with the student picker expanded', () => {
        const methods = (CourseWorks as any).methods
        const ctx: Record<string, any> = {
            work_form: {
                date_for_all_groups: '2026-05-16',
                groups: [
                    { student_ids: [1, 2] },
                ],
            },
            group_dialog_index: null,
            group_dialog_open: false,
            group_dialog_add_students_open: false,
        }

        methods.addGroup.call(ctx)

        expect(ctx.work_form.groups).toHaveLength(2)
        expect(ctx.group_dialog_index).toBe(1)
        expect(ctx.group_dialog_open).toBe(true)
        expect(ctx.group_dialog_add_students_open).toBe(true)
        expect(ctx.work_form.groups[1]).toMatchObject({
            student_ids: [],
            date: '2026-05-16',
            use_individual_grades: false,
        })
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
