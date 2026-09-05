import { describe, expect, it, vi } from 'vitest'
import CourseStudents from '@/pages/admin/teaching/overview/components/CourseStudents.vue'

describe('CourseStudents sorting', () => {
    it('defaults student sort mode to name', () => {
        const data = (CourseStudents as any).data.call({
            emptyBulkEntryForm: () => ({}),
        })

        expect(data.students_sort_mode).toBe('last_name_first_name')
    })

    it('does not show the bulk entry saving state by default', () => {
        const data = (CourseStudents as any).data.call({
            emptyBulkEntryForm: () => ({}),
        })

        expect(data.bulk_entry_saving).toBe(false)
    })

    it('does not include canceled students in the overview list', () => {
        const ctx = {
            selected_course: {
                students_info: [
                    { id: 1, last_name: 'Alpha', first_name: 'A', schoolclass: '1A', canceled_at: '2026-02-15 10:00:00' },
                    { id: 2, last_name: 'Beta', first_name: 'B', schoolclass: '1A', canceled_at: null },
                    { id: 3, last_name: 'Gamma', first_name: 'C', schoolclass: '1A', canceled_at: null },
                    { id: 4, last_name: 'Delta', first_name: 'D', schoolclass: '1A', canceled_at: '2026-02-16 10:00:00' },
                ],
            },
            isStudentCanceled(student: { canceled_at?: string | null }) {
                return !!student?.canceled_at
            },
            compareStudentsBySelectedSort(a: { last_name?: string | null; first_name?: string | null }, b: { last_name?: string | null; first_name?: string | null }) {
                const byLastName = (a.last_name || '').localeCompare(b.last_name || '', 'de', { sensitivity: 'base' })
                if (byLastName !== 0) return byLastName
                return (a.first_name || '').localeCompare(b.first_name || '', 'de', { sensitivity: 'base' })
            },
        }

        const sorted = (CourseStudents as any).computed.sortedSelectedStudents.call(ctx)

        expect(sorted.map((s: { id: number }) => s.id)).toEqual([2, 3])
        expect(sorted.every((s: { canceled_at?: string | null }) => !s.canceled_at)).toBe(true)
    })

    it('counts only non-canceled students in activeStudentsCount', () => {
        const ctx = {
            selected_course: {
                students_info: [
                    { id: 1, canceled_at: null },
                    { id: 2, canceled_at: '2026-02-16 10:00:00' },
                    { id: 3, canceled_at: '' },
                ],
            },
            isStudentCanceled(student: { canceled_at?: string | null }) {
                return !!student?.canceled_at
            },
        }

        const count = (CourseStudents as any).computed.activeStudentsCount.call(ctx)
        expect(count).toBe(2)
    })

    it('returns canceled name class for canceled students', () => {
        const ctx = {
            isStudentCanceled(student: { canceled_at?: string | null }) {
                return !!student?.canceled_at
            },
        }

        const canceledClass = (CourseStudents as any).methods.studentNameClass.call(ctx, { canceled_at: '2026-02-16 10:00:00' })
        const activeClass = (CourseStudents as any).methods.studentNameClass.call(ctx, { canceled_at: null })

        expect(canceledClass).toBe('student-name--canceled')
        expect(activeClass).toBe('')
    })

    it('builds secondary student detail lines for email and last login', () => {
        const methods = (CourseStudents as any).methods

        expect(methods.studentEmailText.call({}, { email: 'student@example.test' })).toBe('student@example.test')
        expect(methods.studentEmailText.call({}, { email: '   ' })).toBe('')
        expect(methods.studentLastLoginText.call({}, { login_at: '24.03.2026  08:15' })).toBe('24.03.2026  08:15')
        expect(methods.studentLastLoginText.call({}, { login_at: null })).toBe('')
    })

    it('formats birth details and uses the table gender symbols beside the name', () => {
        const methods = (CourseStudents as any).methods
        const context = {
            formatDate: methods.formatDate,
            normalizedStudentSex: methods.normalizedStudentSex,
        }

        expect(methods.studentBirthDetails.call(context, { birth_date: '2011-05-20', age: 15 }))
            .toBe('20.05.2011 · 15 Jahre')
        expect(methods.studentBirthDetails.call(context, { birth_date: '2011-05-20', age: null }))
            .toBe('20.05.2011')
        expect(methods.studentSexIcon.call(context, { sex: 'm' })).toBe('mdi-gender-male')
        expect(methods.studentSexColor.call(context, { sex: 'm' })).toBe('blue')
        expect(methods.studentSexIcon.call(context, { sex: 'w' })).toBe('mdi-gender-female')
        expect(methods.studentSexColor.call(context, { sex: 'w' })).toBe('pink')
    })

    it('renders age and last login only when their course settings are enabled', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/components/CourseStudents.vue', 'utf8')
        )

        expect(source).toContain('selected_course?.teaching_show_student_age && studentBirthDetails(student)')
        expect(source).toContain('selected_course?.teaching_show_student_last_login && studentLastLoginText(student)')
        expect(source.indexOf('studentBirthDetails(student)')).toBeGreaterThan(source.indexOf('studentEmailText(student)'))
        expect(source.indexOf('studentBirthDetails(student)')).toBeLessThan(source.indexOf('studentLastLoginText(student)'))
        expect(source).toContain('v-if="studentSexIcon(student)"')
    })

    it('does not render the old per-student pdf button anymore', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/components/CourseStudents.vue', 'utf8')
        )

        expect(source).not.toContain('mdi-file-pdf-box')
        expect(source).not.toContain('studentPerformancePdfUrl')
        expect(source).toContain('studentEmailText(student)')
        expect(source).toContain('studentLastLoginText(student)')
    })

    it('renders clickable student indicators directly in the name line and day overview', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/components/CourseStudents.vue', 'utf8')
        )

        expect(source).toContain('class="student-name-line"')
        expect(source).toContain('<CourseStudentIndicators :student="student"')
        expect(source.indexOf('<CourseStudentIndicators :student="student"')).toBeGreaterThan(source.indexOf('class="student-name-line"'))
        expect(source.indexOf('<CourseStudentIndicators :student="student"')).toBeLessThan(source.indexOf('studentEmailText(student)'))
        expect(source).toContain('<CourseStudentIndicators :student="item.student"')
        expect(source).toContain('@select="$refs.studentNotes.open(student, $event)"')
    })

    it('opens student details from the complete student card outside bulk mode', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/components/CourseStudents.vue', 'utf8')
        )
        const methods = (CourseStudents as any).methods
        const openStudent = vi.fn()
        const context = {
            openStudent,
            show_bulk_entry: false,
        }

        methods.openStudentFromCard.call(context, { id: 11 })

        expect(openStudent).toHaveBeenCalledWith({ id: 11 })
        expect(source).toContain(':link="!show_bulk_entry"')
        expect(source).toContain(':class="{ \'student-list-item--clickable\': !show_bulk_entry }"')
        expect(source).toContain('@click="openStudentFromCard(student)"')
        expect(source).toContain('@click="openStudent(item.student)"')
        expect(source).toContain('.student-list-item--clickable:focus-visible .student-row')
    })

    it('does not open student details when bulk selection is active', () => {
        const methods = (CourseStudents as any).methods
        const openStudent = vi.fn()

        methods.openStudentFromCard.call({
            openStudent,
            show_bulk_entry: true,
        }, { id: 11 })

        expect(openStudent).not.toHaveBeenCalled()
    })

    it('shows the bulk entry button loading state before saving entries', async () => {
        const methods = (CourseStudents as any).methods
        let continueNextTick: (() => void) | null = null
        const store = vi.fn().mockResolvedValue(true)
        const cancelBulkEntry = vi.fn()
        const ctx: Record<string, any> = {
            bulk_entry_saving: false,
            selected_course: {
                id: 8,
                students_info: [{ id: 11 }, { id: 12 }],
            },
            bulk_entry_form: {
                student_ids: [11],
                type: 'MA',
                grade: '1',
                date: '2026-04-30',
                description: 'Aktive Mitarbeit',
            },
            entryStore: { store },
            cancelBulkEntry,
            $nextTick: vi.fn(() => new Promise<void>((resolve) => {
                continueNextTick = resolve
            })),
        }

        const promise = methods.saveBulkEntry.call(ctx)

        expect(ctx.bulk_entry_saving).toBe(true)
        expect(ctx.$nextTick).toHaveBeenCalledTimes(1)
        expect(store).not.toHaveBeenCalled()

        continueNextTick?.()
        await promise

        expect(store).toHaveBeenCalledWith({
            teaching_course_id: 8,
            user_id: 11,
            type: 'MA',
            grade: '1',
            date: '2026-04-30',
            description: 'Aktive Mitarbeit',
        })
        expect(cancelBulkEntry).toHaveBeenCalledTimes(1)
        expect(ctx.bulk_entry_saving).toBe(false)
    })
})

describe('CourseStudents selected date label', () => {
    it('formats selected date label in compact form', () => {
        const computed = (CourseStudents as any).computed
        const methods = (CourseStudents as any).methods

        const ctx: Record<string, unknown> = {
            selectedCourseDateForCourse: {
                date: '2026-03-02',
                hours: [6, 5],
            },
            school_hours: [
                { hour: 5, from: '11:50:00', until: '12:40:00' },
                { hour: 6, from: '12:45:00', until: '13:35:00' },
            ],
            getWeekdayShort: methods.getWeekdayShort,
            formatDateShort: methods.formatDateShort,
            formatCourseDateHoursCompact: methods.formatCourseDateHoursCompact,
            formatCourseDateTimeRange: methods.formatCourseDateTimeRange,
            formatTimeShort: methods.formatTimeShort,
        }
        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)

        const label = computed.selectedCourseDateLabel.call(ctx)

        expect(label).toBe('Mo, 02.03. - 5.-6. Std (11:50-13:35)')
    })

    it('formats discontinuous hours as compact groups', () => {
        const methods = (CourseStudents as any).methods

        const label = methods.formatCourseDateHoursCompact.call({}, [2, 4, 5])

        expect(label).toBe('2. Std, 4.-5. Std')
    })
})

describe('CourseStudents attendance editing', () => {
    it.each([
        ['2025/26', true],
        ['2026/27', false],
        ['2027/28', false],
    ])('allows attendance editing for %s: %s', (concerns, expected) => {
        const canEditAttendance = (CourseStudents as any).computed.canEditAttendance

        expect(canEditAttendance.call({
            config: { selected_schoolyear: { concerns } },
        })).toBe(expected)
    })

    it('recognizes a school year embedded in its name', () => {
        const canEditAttendance = (CourseStudents as any).computed.canEditAttendance

        expect(canEditAttendance.call({
            config: { selected_schoolyear: { name: 'Schuljahr 2026/27' } },
        })).toBe(false)
    })

    it('removes the attendance actions from the student panel from 2026/27 onward', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/overview/components/CourseStudents.vue', 'utf8')
        )

        expect(source).toContain('v-if="canEditAttendance && selectedCourseDateForCourse && !isDayOverviewMode && !show_bulk_entry"')
        expect(source).toContain('v-if="canEditAttendance && selectedCourseDateForCourse"')
    })

    it('does not toggle one student when attendance editing is disabled', () => {
        const getAttendanceMap = vi.fn()

        ;(CourseStudents as any).methods.toggleStudentPresence.call({
            canEditAttendance: false,
            selectedCourseDateForCourse: { id: 277 },
            getAttendanceMap,
        }, { id: 16 })

        expect(getAttendanceMap).not.toHaveBeenCalled()
    })

    it('does not check attendance when attendance editing is disabled', async () => {
        const persistAttendance = vi.fn()

        await (CourseStudents as any).methods.toggleAttendanceChecked.call({
            canEditAttendance: false,
            selectedCourseDateForCourse: { id: 277 },
            savingAttendance: false,
            persistAttendance,
        })

        expect(persistAttendance).not.toHaveBeenCalled()
    })
})

describe('CourseStudents course-specific schema', () => {
    it('prefers the selected course schema snapshot for works and semester count', () => {
        const computed = (CourseStudents as any).computed
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

    it('uses the assigned entry-area definitions for the new bulk action options', () => {
        const computed = (CourseStudents as any).computed
        const context: Record<string, any> = {
            uses_entry_areas_for_grading_schema: true,
            selected_course: {
                teaching_entry_area: {
                    id: 4,
                    entry_definitions: [
                        {
                            category: 'Benotung',
                            short_name: 'MÜ',
                            name: 'Mündliche Übung',
                            has_properties: true,
                            properties_mode: 'fixed',
                            fixed_properties: ['Sehr gut', 'Gut'],
                        },
                        { category: 'Verhalten', short_name: 'ZV', name: 'Zuverlässigkeit' },
                    ],
                },
                teacher_teaching_schema: {
                    works: [{ short_name: 'ALT', name: 'Alte Option', grades: [{ grade: '1' }] }],
                },
            },
            bulk_entry_form: { type: 'MÜ', grade: '' },
        }

        context.selectedCourseSchema = computed.selectedCourseSchema.call(context)
        context.teachingWorks = computed.teachingWorks.call(context)
        context.usesNewBulkEntryDefinitions = computed.usesNewBulkEntryDefinitions.call(context)

        expect(context.teachingWorks.map((definition: { short_name: string }) => definition.short_name)).toEqual(['MÜ'])
        expect(computed.workTypeItems.call(context)).toEqual([{ title: 'MÜ - Mündliche Übung', value: 'MÜ' }])
        expect(computed.gradeItemsForType.call(context)).toEqual([
            { title: 'Sehr gut', value: 'Sehr gut' },
            { title: 'Gut', value: 'Gut' },
        ])
        expect(computed.bulkGradeInputMode.call(context)).toBe('fixed')
        expect(computed.bulkGradeLabel.call(context)).toBe('Eigenschaft')
    })

    it('allows a new bulk entry without a property when its definition has none', () => {
        const computed = (CourseStudents as any).computed
        const context: Record<string, any> = {
            hasStudents: true,
            usesNewBulkEntryDefinitions: true,
            teachingWorks: [{ short_name: 'OK', has_properties: false }],
            bulk_entry_form: {
                student_ids: [11],
                type: 'OK',
                grade: '',
                description: '',
            },
        }

        context.bulkGradeInputMode = computed.bulkGradeInputMode.call(context)

        expect(context.bulkGradeInputMode).toBe('none')
        expect(computed.bulkEntryEnabled.call(context)).toBe(true)
    })
})
