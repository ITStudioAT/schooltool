import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import CourseInfos from '@/pages/admin/teaching/overview/components/CourseInfos.vue'

describe('CourseInfos representative countdowns', () => {
    function toLocalDateString(value: Date): string {
        const year = value.getFullYear()
        const month = String(value.getMonth() + 1).padStart(2, '0')
        const day = String(value.getDate()).padStart(2, '0')
        return `${year}-${month}-${day}`
    }

    it('formats "Findet statt in" with day/hour/minute and omits zero day/hour', () => {
        const computed = (CourseInfos as any).computed
        const methods = (CourseInfos as any).methods
        const now = new Date(2026, 2, 1, 7, 0, 0)

        const ctx: Record<string, unknown> = {
            nowTs: now.getTime(),
            selected_course: {
                course_dates: [
                    { date: toLocalDateString(new Date(2026, 2, 2)), hours: [1] },
                ],
            },
            school_hours: [{ hour: 1, from: '08:15:00', until: '09:05:00' }],
            lessonStartFromHour: methods.lessonStartFromHour,
            padTwo: methods.padTwo,
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)
        ctx.nextCourseStartAt = computed.nextCourseStartAt.call(ctx)

        expect(computed.startsInLabel.call(ctx)).toBe('1d 01h 15m')
    })

    it('formats "End in" and hides hour when it is zero', () => {
        const computed = (CourseInfos as any).computed
        const methods = (CourseInfos as any).methods
        const now = new Date(2026, 2, 2, 8, 10, 0)

        const ctx: Record<string, unknown> = {
            nowTs: now.getTime(),
            selected_course: {
                course_dates: [
                    { date: toLocalDateString(now), hours: [1] },
                ],
            },
            school_hours: [{ hour: 1, from: '08:00:00', until: '08:50:00' }],
            lessonStartFromHour: methods.lessonStartFromHour,
            lessonEndFromHour: methods.lessonEndFromHour,
            padTwo: methods.padTwo,
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)
        ctx.activeCourseEndAt = computed.activeCourseEndAt.call(ctx)

        expect(computed.endsInLabel.call(ctx)).toBe('40m')
    })

    it('uses active representative state for label/value/class', () => {
        const computed = (CourseInfos as any).computed
        const methods = (CourseInfos as any).methods
        const now = new Date(2026, 2, 2, 8, 10, 0)

        const ctx: Record<string, unknown> = {
            nowTs: now.getTime(),
            selected_course: {
                course_dates: [
                    { date: toLocalDateString(now), hours: [1] },
                ],
            },
            school_hours: [{ hour: 1, from: '08:00:00', until: '08:50:00' }],
            lessonStartFromHour: methods.lessonStartFromHour,
            lessonEndFromHour: methods.lessonEndFromHour,
            padTwo: methods.padTwo,
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)
        ctx.activeCourseEndAt = computed.activeCourseEndAt.call(ctx)
        ctx.endsInLabel = computed.endsInLabel.call(ctx)
        ctx.startsInLabel = computed.startsInLabel.call(ctx)
        ctx.isCourseActiveNow = computed.isCourseActiveNow.call(ctx)

        expect(computed.representativeCountdownLabel.call(ctx)).toBe('Endet in:')
        expect(computed.representativeCountdownValue.call(ctx)).toBe('40m')
        expect(computed.representativeCountdownValueClass.call(ctx)).toBe('text-body-1 font-weight-medium text-primary')
    })

    it('uses upcoming representative state for label/value/class when not active', () => {
        const computed = (CourseInfos as any).computed
        const methods = (CourseInfos as any).methods
        const now = new Date(2026, 2, 1, 7, 0, 0)

        const ctx: Record<string, unknown> = {
            nowTs: now.getTime(),
            selected_course: {
                course_dates: [
                    { date: toLocalDateString(new Date(2026, 2, 2)), hours: [1] },
                ],
            },
            school_hours: [{ hour: 1, from: '08:15:00', until: '09:05:00' }],
            lessonStartFromHour: methods.lessonStartFromHour,
            lessonEndFromHour: methods.lessonEndFromHour,
            padTwo: methods.padTwo,
        }

        ctx.schoolHoursByHour = computed.schoolHoursByHour.call(ctx)
        ctx.activeCourseEndAt = computed.activeCourseEndAt.call(ctx)
        ctx.nextCourseStartAt = computed.nextCourseStartAt.call(ctx)
        ctx.endsInLabel = computed.endsInLabel.call(ctx)
        ctx.startsInLabel = computed.startsInLabel.call(ctx)
        ctx.isCourseActiveNow = computed.isCourseActiveNow.call(ctx)

        expect(computed.representativeCountdownLabel.call(ctx)).toBe('Findet statt in:')
        expect(computed.representativeCountdownValue.call(ctx)).toBe('1d 01h 15m')
        expect(computed.representativeCountdownValueClass.call(ctx)).toBe('text-body-2 font-weight-medium')
    })

    it('renders representative labels in template', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseInfos.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('Findet statt in:')
        expect(source).toContain('Endet in:')
        expect(source).toContain('{{ representativeCountdownLabel }}')
        expect(source).toContain('{{ representativeCountdownValue }}')
    })

    it('renders non-grade info blocks in a two-column grid', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseInfos.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<div class="course-infos-grid mt-2">')
        expect(source).toContain('class="course-info-card"')
        expect(source).toContain('class="course-info-card course-info-summary-card"')
        expect(source).toContain('class="course-info-summary-card__content"')
        expect(source).toContain('class="course-info-block course-info-block--countdown"')
        expect(source).toContain('class="course-info-block course-info-block--schema"')
        expect(source).toContain('class="course-info-block course-info-block--description"')
        expect(source).toContain('class="mt-3 course-infos-grades-card"')
        expect(source).toContain('.course-infos-grid {')
        expect(source).toContain('grid-template-columns: repeat(2, minmax(0, 1fr));')
        expect(source).toContain('.course-info-block--countdown {')
        expect(source).toContain('.course-info-block--schema {')
        expect(source).toContain('.course-info-block--description {')
        expect(source).toContain('@media (max-width: 700px)')
        expect(source).toContain('grid-template-columns: minmax(0, 1fr);')
    })

    it('does not render a hide eye button in the infos header', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseInfos.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).not.toContain('mdi-eye-off-outline')
        expect(source).not.toContain('title="Ausblenden"')
        expect(source).not.toContain('@click="show_infos = false"')
    })

    it('renders open notifications as colored blocks', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseInfos.vue',
        )
        const source = readFileSync(componentPath, 'utf8')
        const methods = (CourseInfos as any).methods
        const firstTypeClass = methods.notificationEntryBackgroundClass.call({}, { type: 'INF' })
        const sameTypeClass = methods.notificationEntryBackgroundClass.call({}, { type: 'INF' })
        const emptyTypeClass = methods.notificationEntryBackgroundClass.call({}, { type: '' })

        expect(source).toContain('class="open-notifications-list"')
        expect(source).toContain('class="open-notification-item"')
        expect(source).toContain('class="notification-row open-notification-block d-flex flex-wrap align-start ga-2 w-100" :class="notificationEntryBackgroundClass(entry)"')
        expect(source).toContain('class="open-notification-student-name"')
        expect(source).not.toContain('<v-chip size="x-small" variant="outlined" class="chip-truncate">{{ studentLabel(entry.user_id) }}</v-chip>')
        expect(source).toContain('.open-notification-block {')
        expect(source).toContain('border-radius: 8px;')
        expect(source).toContain('.open-notification-student-name {')
        expect(source).toContain('flex-basis: 100%;')
        expect(source).toContain('font-weight: 750;')
        expect(source).toContain('.open-notification-block--type-1 {')
        expect(firstTypeClass).toBe(sameTypeClass)
        expect(firstTypeClass).toMatch(/^open-notification-block--type-[1-6]$/)
        expect(emptyTypeClass).toBe('open-notification-block--type-empty')
    })

    it('preserves line breaks in open notification descriptions', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseInfos.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('.notification-description {')
        expect(source).toContain('white-space: pre-wrap;')
        expect(source).toContain('overflow-wrap: anywhere;')
    })

    it('uses zebra striping for calculated grade student rows', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseInfos.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('.grades-table tbody tr:nth-child(even) td {')
        expect(source).toContain('background-color: rgba(37, 99, 235, 0.045);')
    })
})

describe('CourseInfos course-specific definitions', () => {
    it('prefers the selected course schema and notification definitions', () => {
        const computed = (CourseInfos as any).computed
        const ctx: Record<string, unknown> = {
            selected_course: {
                teaching_schema_id: 'schema-teacher',
                teacher_teaching_schema: {
                    id: 'schema-teacher',
                    name: 'Lehrkraft-Schema',
                    works: [{ short_name: 'MA' }],
                    grading: { semester_count: 2 },
                },
                teacher_teaching_notifications: [{ short_name: 'INF', name: 'Info Lehrkraft' }],
            },
            teachingStore: {
                schemaById: () => ({ id: 'schema-global', name: 'Global-Schema' }),
                settings: { teaching_notifications: [{ short_name: 'INF', name: 'Info Global' }] },
            },
        }

        ctx.selectedCourseSchema = computed.selectedCourseSchema.call(ctx)
        ctx.selectedCourseEntryArea = computed.selectedCourseEntryArea.call(ctx)

        expect((ctx.selectedCourseSchema as any)).toMatchObject({ id: 'schema-teacher', name: 'Lehrkraft-Schema' })
        expect(computed.schemaName.call(ctx)).toBe('Lehrkraft-Schema')
        expect(computed.gradingSchema.call(ctx)).toBe(ctx.selectedCourseSchema)

        const notificationTypes = computed.notificationTypesByShort.call(ctx)
        expect(notificationTypes.get('INF')).toBe('Info Lehrkraft')
    })

    it('uses the assigned 2026/27 entry area instead of the legacy Standard schema', () => {
        const computed = (CourseInfos as any).computed
        const ctx: Record<string, unknown> = {
            selected_course: {
                teaching_schema_id: 'legacy-standard',
                teacher_teaching_schema: { id: 'legacy-standard', name: 'Standard' },
                teaching_entry_area: { id: 14, name: 'DGB' },
            },
            uses_entry_areas_for_grading_schema: false,
        }

        ctx.selectedCourseSchema = computed.selectedCourseSchema.call(ctx)
        ctx.selectedCourseEntryArea = computed.selectedCourseEntryArea.call(ctx)

        expect(computed.schemaName.call(ctx)).toBe('DGB')
        expect(computed.gradingSchema.call(ctx)).toBeNull()
    })

    it('hides empty Fachinfos instead of rendering a placeholder', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseInfos.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<div v-if="selected_course.description" class="course-info-block course-info-block--description">')
        expect(source).not.toContain('Keine Fachinfos vorhanden.')
    })

    it('restores visible grade columns from the route query', () => {
        const methods = (CourseInfos as any).methods
        const ctx: Record<string, unknown> = {
            infos_show_grade_sem1: false,
            infos_show_grade_sem2: false,
            infos_show_grade_year: false,
            restoringGradeColumns: false,
            normalizeGradeQueryValue: methods.normalizeGradeQueryValue,
            parseGradeColumns: methods.parseGradeColumns,
            restoreGradeColumns: methods.restoreGradeColumns,
        }

        methods.restoreGradeColumnsFromRoute.call(ctx, 'sem1,year')

        expect(ctx.infos_show_grade_sem1).toBe(true)
        expect(ctx.infos_show_grade_sem2).toBe(false)
        expect(ctx.infos_show_grade_year).toBe(true)
    })

    it('persists visible grade columns into the route query', async () => {
        const methods = (CourseInfos as any).methods
        const replace = vi.fn().mockResolvedValue(undefined)
        const ctx: Record<string, unknown> = {
            infos_show_grade_sem1: true,
            infos_show_grade_sem2: false,
            infos_show_grade_year: true,
            $route: {
                path: '/admin/teaching',
                query: {
                    course: '6',
                    panel: 'infos',
                },
            },
            $router: { replace },
            hasTwoSemesters: true,
            normalizeGradeQueryValue: methods.normalizeGradeQueryValue,
            currentGradeColumns: methods.currentGradeColumns,
            gradeColumnsQueryValue: methods.gradeColumnsQueryValue,
        }

        methods.syncGradeColumnsToRoute.call(ctx)

        expect(replace).toHaveBeenCalledWith({
            path: '/admin/teaching',
            query: {
                course: '6',
                panel: 'infos',
                grades: 'sem1,year',
            },
        })
    })

    it('restores student-visible grade columns from the saved settings', () => {
        const methods = (CourseInfos as any).methods
        const ctx: Record<string, unknown> = {
            student_grade_visibility_show_sem1: false,
            student_grade_visibility_show_sem2: false,
            student_grade_visibility_show_year: false,
            restoringStudentGradeColumns: false,
        }

        methods.restoreStudentGradeColumns.call(ctx, {
            show_sem1: false,
            show_sem2: true,
            show_year: true,
        })

        expect(ctx.student_grade_visibility_show_sem1).toBe(false)
        expect(ctx.student_grade_visibility_show_sem2).toBe(true)
        expect(ctx.student_grade_visibility_show_year).toBe(true)
    })

    it('persists student-visible grade columns separately from the teacher infos view', async () => {
        const methods = (CourseInfos as any).methods
        const saveSettings = vi.fn().mockResolvedValue(true)
        const ctx: Record<string, unknown> = {
            teachingStore: { saveSettings },
            selected_course: { id: 6 },
            student_grade_visibility_show_sem1: true,
            student_grade_visibility_show_sem2: false,
            student_grade_visibility_show_year: true,
            currentStudentGradeColumns: methods.currentStudentGradeColumns,
            gradeColumnsMatch: methods.gradeColumnsMatch,
            persistedStudentGradeColumns: {
                show_sem1: false,
                show_sem2: false,
                show_year: false,
            },
        }

        await methods.persistStudentGradeColumns.call(ctx)

        expect(saveSettings).toHaveBeenCalledWith({
            teaching_course_id: 6,
            teaching_student_grade_columns: {
                show_sem1: true,
                show_sem2: false,
                show_year: true,
            },
        }, {
            notifySuccess: false,
        })
        expect((ctx.selected_course as any).teaching_student_grade_columns).toEqual({
            show_sem1: true,
            show_sem2: false,
            show_year: true,
        })
    })

    it('treats semester two and year columns as hidden for one-semester schemas', () => {
        const computed = (CourseInfos as any).computed
        const ctx = {
            infos_show_grade_sem1: false,
            infos_show_grade_sem2: true,
            infos_show_grade_year: true,
            hasTwoSemesters: false,
        }

        expect(computed.anyGradeColumnVisible.call(ctx)).toBe(false)
    })

    it('initializes grade data on mount when the course and visible columns already exist', async () => {
        const methods = (CourseInfos as any).methods
        const ensureCourseStudentCollections = vi.fn()
        const loadGradeData = vi.fn().mockResolvedValue(undefined)
        const ctx: Record<string, unknown> = {
            selected_course: { id: 6 },
            anyGradeColumnVisible: true,
            gradesDataLoaded: false,
            courseStore: { ensureCourseStudentCollections },
            loadGradeData,
        }

        await methods.initializeGradeState.call(ctx)

        expect(ensureCourseStudentCollections).toHaveBeenCalledWith(ctx.selected_course)
        expect(loadGradeData).toHaveBeenCalledTimes(1)
    })

    it('renders a dedicated student visibility section for calculated grades', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseInfos.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('Für Schüler:innen sichtbar')
        expect(source).toContain('Diese Auswahl steuert ausschließlich die Berechnungen im Schüler:innen-Bereich.')
        expect(source).toContain('student_grade_visibility_show_sem1')
        expect(source).toContain('student_grade_visibility_show_sem2')
        expect(source).toContain('student_grade_visibility_show_year')
        expect(source).toContain('teaching_student_grade_columns')
    })

    it('renders per-course student display settings', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/teaching/overview/components/CourseInfos.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('Schüler:innen-Anzeige')
        expect(source).toContain('label="Alter"')
        expect(source).toContain('label="Last Login"')
        expect(source).toContain("saveStudentDisplaySetting('teaching_show_student_age', $event)")
        expect(source).toContain("saveStudentDisplaySetting('teaching_show_student_last_login', $event)")
    })

    it('persists and refreshes a student display setting', async () => {
        const methods = (CourseInfos as any).methods
        const update = vi.fn().mockResolvedValue(true)
        const refreshedCourse = {
            id: 6,
            teaching_show_student_age: true,
            teaching_show_student_last_login: false,
        }
        const refreshCourseById = vi.fn().mockResolvedValue(refreshedCourse)
        const ctx: Record<string, any> = {
            selected_course: {
                id: 6,
                title: 'Deutsch',
                classes: ['1A'],
                teaching_schema_id: 'schema-standard',
                teaching_show_student_age: false,
                teaching_show_student_last_login: false,
            },
            student_display_show_age: false,
            student_display_show_last_login: false,
            isSavingInfo: false,
            courseStore: { update, refreshCourseById },
            runInfoMutation(_action: string, callback: () => Promise<boolean>) {
                return callback()
            },
            restoreStudentDisplaySettings: methods.restoreStudentDisplaySettings,
        }

        await methods.saveStudentDisplaySetting.call(ctx, 'teaching_show_student_age', true)

        expect(update).toHaveBeenCalledWith(expect.objectContaining({
            id: 6,
            teaching_show_student_age: true,
        }))
        expect(refreshCourseById).toHaveBeenCalledWith(6)
        expect(ctx.student_display_show_age).toBe(true)
        expect(ctx.student_display_show_last_login).toBe(false)
    })
})
