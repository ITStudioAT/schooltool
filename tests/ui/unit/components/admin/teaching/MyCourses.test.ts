import { describe, expect, it, vi } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import MyCourses from '@/pages/admin/teaching/overview/components/MyCourses.vue'

describe('MyCourses counts', () => {
    it('defaults student sort mode to name', () => {
        const data = (MyCourses as any).data.call({})

        expect(data.students_sort_mode).toBe('last_name_first_name')
    })

    it('counts only non-canceled students in activeSelectedStudentsCount', () => {
        const ctx = {
            data: {
                students_info: [
                    { id: 1, canceled_at: null },
                    { id: 2, canceled_at: '2026-02-16 12:00:00' },
                    { id: 3, canceled_at: '' },
                ],
            },
            isStudentCanceled(student: { canceled_at?: string | null }) {
                return !!student?.canceled_at
            },
        }

        const count = (MyCourses as any).computed.activeSelectedStudentsCount.call(ctx)
        expect(count).toBe(2)
    })

    it('returns canceled name class for canceled students', () => {
        const ctx = {
            isStudentCanceled(student: { canceled_at?: string | null }) {
                return !!student?.canceled_at
            },
        }

        const canceledClass = (MyCourses as any).methods.studentNameClass.call(ctx, { canceled_at: '2026-02-16 12:00:00' })
        const activeClass = (MyCourses as any).methods.studentNameClass.call(ctx, { canceled_at: null })

        expect(canceledClass).toBe('student-name--canceled')
        expect(activeClass).toBe('')
    })

    it('normalizes and joins classes for readable labels', () => {
        const ctx = {
            courseClasses: (MyCourses as any).methods.courseClasses,
        }
        const course = {
            classes: [' 2B ', '', '5A', null],
        }

        const classes = (MyCourses as any).methods.courseClasses.call(ctx, course)
        const text = (MyCourses as any).methods.courseClassesText.call(ctx, course)

        expect(classes).toEqual(['2B', '5A'])
        expect(text).toBe('2B, 5A')
    })

    it('does not duplicate the primary class in secondary class chips', () => {
        const ctx = {
            courseClasses: (MyCourses as any).methods.courseClasses,
        }
        const course = {
            classes: ['7A-BU', '7A-DG', '7S'],
        }

        const primaryClass = (MyCourses as any).methods.primaryCourseClass.call(ctx, course)
        const secondaryClasses = (MyCourses as any).methods.secondaryCourseClasses.call(ctx, course)
        const countLabel = (MyCourses as any).methods.courseClassesCountLabel.call(ctx, course)

        expect(primaryClass).toBe('7A-BU')
        expect(secondaryClasses).toEqual(['7A-DG', '7S'])
        expect(secondaryClasses).not.toContain(primaryClass)
        expect(countLabel).toBe('3 Klassen')
    })

    it('shows a problem marker beside course names when the course has no dates', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/admin/teaching/overview/components/MyCourses.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source.match(/courseStore\?\.courseHasProblems\(course\)/g)).toHaveLength(3)
        expect(source.match(/aria-label="Probleme im Fach">!<\/span>/g)).toHaveLength(3)
    })

    it('toggles between classic and alternative course view', () => {
        const ctx = {
            courses_view_variant: 'v1',
            setCoursesViewVariant(variant: string) {
                this.courses_view_variant = variant
            },
        }

        ;(MyCourses as any).methods.toggleCoursesViewVariant.call(ctx)
        expect(ctx.courses_view_variant).toBe('v2')

        ;(MyCourses as any).methods.toggleCoursesViewVariant.call(ctx)
        expect(ctx.courses_view_variant).toBe('v1')
    })

    it('starts a fresh dialog when a pending new-course token is emitted', () => {
        const newCourse = () => true
        const ctx = {
            newCourseCalled: 0,
            newCourse() {
                this.newCourseCalled++
            },
        }

        ;(MyCourses as any).watch['courseStore.pending_new_course_token'].call(ctx, 4)

        expect(ctx.newCourseCalled).toBe(1)
    })

    it('consumes a pending course edit when the editor component mounts', async () => {
        const course = { id: 16, title: '2B - DGB' }
        const editCourse = vi.fn().mockResolvedValue(undefined)
        const pendingEditWatcher = (MyCourses as any).watch['courseStore.pending_edit_course_id']
        const ctx = {
            courses: [course],
            courseStore: { pending_edit_course_id: 16 },
            editCourse,
        }

        await pendingEditWatcher.handler.call(ctx, 16)

        expect(pendingEditWatcher.immediate).toBe(true)
        expect(ctx.courseStore.pending_edit_course_id).toBeNull()
        expect(editCourse).toHaveBeenCalledWith(course)
    })

    it('requires the schoolyear-specific grading schema selection', () => {
        const computed = (MyCourses as any).computed.hasSelectedGradingSchema

        expect(
            computed.call({
                uses_entry_areas_for_grading_schema: false,
                data: { teaching_schema_id: 'legacy-schema', teaching_entry_area_id: null },
            })
        ).toBe(true)
        expect(
            computed.call({
                uses_entry_areas_for_grading_schema: true,
                data: { teaching_schema_id: 'legacy-schema', teaching_entry_area_id: null },
            })
        ).toBe(false)
        expect(
            computed.call({
                uses_entry_areas_for_grading_schema: true,
                data: { teaching_schema_id: null, teaching_entry_area_id: 11 },
            })
        ).toBe(true)
    })

    it('builds one class head row per selected class and restores remembered teachers', () => {
        const syncClassHeadEmailRows = (MyCourses as any).methods.syncClassHeadEmailRows
        const context = {
            data: { class_head_emails: [] },
            class_head_email_drafts: {},
            class_head_emails: [
                { class_name: '1A', teacher_1_id: 4, teacher_2_id: 7 },
            ],
        }

        syncClassHeadEmailRows.call(context, ['1A', '1B'])

        expect(context.data.class_head_emails).toEqual([
            { class_name: '1A', teacher_1_id: 4, teacher_2_id: 7 },
            { class_name: '1B', teacher_1_id: null, teacher_2_id: null },
        ])
    })

    it('preserves class head teacher drafts when a class is removed and reselected', () => {
        const syncClassHeadEmailRows = (MyCourses as any).methods.syncClassHeadEmailRows
        const context = {
            data: {
                class_head_emails: [{ class_name: '1A', teacher_1_id: 9, teacher_2_id: null }],
            },
            class_head_email_drafts: {},
            class_head_emails: [],
        }

        syncClassHeadEmailRows.call(context, [])
        syncClassHeadEmailRows.call(context, ['1A'])

        expect(context.data.class_head_emails).toEqual([
            { class_name: '1A', teacher_1_id: 9, teacher_2_id: null },
        ])
    })

    it('renders exactly two teacher selectors for every selected class head row', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/admin/teaching/overview/components/MyCourses.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('v-for="classHeadEmail in data.class_head_emails"')
        expect(source.match(/v-model="classHeadEmail\.teacher_[12]_id"/g)).toHaveLength(2)
        expect(source.match(/<v-autocomplete/g)).toHaveLength(2)
        expect(source).toContain('Klassenvorstand')
        expect(source).not.toContain('v-model="classHeadEmail.email_1"')
        expect(source).not.toContain('v-model="classHeadEmail.email_2"')
    })

    it('excludes the teacher selected in the other class head selector', () => {
        const classHeadTeacherItemsFor = (MyCourses as any).methods.classHeadTeacherItemsFor
        const context = {
            classHeadTeacherItems: [
                { title: 'Huber, Anna (HA)', value: 4 },
                { title: 'Moser, Paul (MP)', value: 7 },
            ],
        }

        expect(classHeadTeacherItemsFor.call(context, { teacher_1_id: 4, teacher_2_id: 7 }, 1)).toEqual([
            { title: 'Huber, Anna (HA)', value: 4 },
        ])
    })

    it('renders legacy schemas through 2025/26 and Bereiche afterward', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/admin/teaching/overview/components/MyCourses.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('v-if="uses_entry_areas_for_grading_schema"')
        expect(source).toContain('v-for="item in entryAreaItems"')
        expect(source).toContain('data.teaching_entry_area_id === item.value')
        expect(source).toContain('v-for="item in schemaItems"')
        expect(source).toContain('data.teaching_schema_id === item.value')
        expect(source).toContain('Keine Benotungsschemas unter Einstellungen &gt; Einträge vorhanden.')
    })

    it('offers course-owner entry areas and binds the assignment in the course form', () => {
        const entryAreaItems = (MyCourses as any).computed.entryAreaItems.call({
            data: {
                id: 6,
                teacher_teaching_entry_areas: [
                    { id: 5, name: 'M-INF Unterstufe' },
                    { id: 11, name: 'DGB' },
                ],
            },
            entry_areas: [{ id: 99, name: 'Admin-Bereich' }],
        })
        const componentPath = resolve(process.cwd(), 'resources/js/pages/admin/teaching/overview/components/MyCourses.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(entryAreaItems).toEqual([
            { title: 'DGB', value: 11 },
            { title: 'M-INF Unterstufe', value: 5 },
        ])
        expect(source).toContain('v-for="item in entryAreaItems"')
        expect(source).toContain('data.teaching_entry_area_id = data.teaching_entry_area_id === item.value ? null : item.value')
    })

    it('uses a two-column course button layout on small screens', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/admin/teaching/overview/components/MyCourses.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="my-courses-v1-chip-group"')
        expect(source).toContain('class="my-courses-v1-mobile-grid"')
        expect(source).toContain('class="my-courses-v1-mobile-button"')
        expect(source).toContain('.my-courses-v1-chip-group {')
        expect(source).toContain('display: none;')
        expect(source).toContain('.my-courses-v1-mobile-grid {')
        expect(source).toContain('display: grid;')
        expect(source).toContain('grid-template-columns: repeat(2, minmax(0, 1fr));')
        expect(source).toContain('.my-courses-v2-grid')
    })

    it('moves course actions into a dedicated small-screen row', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/admin/teaching/overview/components/MyCourses.vue')
        const source = readFileSync(componentPath, 'utf8')
        const editButtonIndex = source.indexOf('icon="mdi-pencil"')
        const deleteButtonIndex = source.indexOf('icon="mdi-delete"')
        const mobilePlusButtonIndex = source.indexOf('class="my-courses-course-actions__new"')

        expect(source).toContain('class="w-100 d-flex flex-row justify-end my-courses-course-actions"')
        expect(source).toContain('class="d-flex flex-row align-center ga-2 my-courses-course-actions__buttons"')
        expect(source).toContain('class="my-courses-header-action--new"')
        expect(source).toContain('.my-courses-header-action--new')
        expect(source).toContain('display: none;')
        expect(source).toContain('.my-courses-course-actions__new')
        expect(source).toContain('display: inline-flex;')
        expect(editButtonIndex).toBeLessThan(deleteButtonIndex)
        expect(deleteButtonIndex).toBeLessThan(mobilePlusButtonIndex)
    })
})
