import { readFileSync } from 'node:fs'
import { afterEach, describe, expect, it, vi } from 'vitest'
import TimetableV3 from '@/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue'

const WORKSPACE_ID = '11111111-1111-4111-8111-111111111111'

const timetableCalculationStreamResponse = (events: unknown[], options: { ok?: boolean, status?: number } = {}) => ({
    ok: options.ok ?? true,
    status: options.status ?? 200,
    body: null,
    text: vi.fn().mockResolvedValue(events.map(event => (
        typeof event === 'string' ? event : JSON.stringify(event)
    )).join('\n')),
})

function timetablePageFixture(
    total: number,
    currentPage = 1,
    unfilteredTotal = total,
    includeSaturday = true,
    excludeSaturdayTotal = total,
    freeDays: number | null = null,
    freeDayValues: Array<{ value: number, count: number }> = [],
    includeSaturdayTotal = unfilteredTotal,
    anyFreeDayTotal = total,
) {
    const perPage = 100
    const offset = (currentPage - 1) * perPage
    const count = Math.min(perPage, Math.max(0, total - offset))
    const timetables = Array.from({ length: count }, (_, index) => {
        const position = offset + index + 1

        return {
            key: `timetable-${position}`,
            number: position,
            slots: {},
            type: 'full_green',
        }
    })

    return {
        timetables,
        timetables_meta: {
            current_page: currentPage,
            per_page: perPage,
            last_page: Math.max(1, Math.ceil(total / perPage)),
            total,
            unfiltered_total: unfilteredTotal,
            offset,
            from: count > 0 ? offset + 1 : null,
            to: count > 0 ? offset + count : null,
            option_counts: {
                include_saturday: includeSaturdayTotal,
                exclude_saturday: excludeSaturdayTotal,
                free_days: {
                    any: anyFreeDayTotal,
                    maximum: freeDayValues[0]?.value ?? 0,
                    values: freeDayValues,
                },
            },
            filters: { include_saturday: includeSaturday, free_days: freeDays },
        },
    }
}

function timetableResultFixture(
    total: number,
    currentPage = 1,
    unfilteredTotal = total,
    includeSaturday = true,
    excludeSaturdayTotal = total,
    freeDays: number | null = null,
    freeDayValues: Array<{ value: number, count: number }> = [],
    includeSaturdayTotal = unfilteredTotal,
    anyFreeDayTotal = total,
) {
    return {
        id: 42,
        fingerprint: 'a'.repeat(64),
        summary: {
            possible_timetable_count: unfilteredTotal + 956,
            timetable_count: unfilteredTotal,
        },
        ...timetablePageFixture(
            total,
            currentPage,
            unfilteredTotal,
            includeSaturday,
            excludeSaturdayTotal,
            freeDays,
            freeDayValues,
            includeSaturdayTotal,
            anyFreeDayTotal,
        ),
    }
}

function timetablePagingContext(result = timetableResultFixture(500)) {
    return {
        currentStep: 'creation',
        planningMode: 'with_student',
        selectedStudentCode: '1001',
        timetableFilters: { include_saturday: true, free_days: null },
        timetableCalculationError: '',
        timetableCalculationResult: result,
        timetableCalculationResultMatchesCurrentDraft: vi.fn().mockReturnValue(true),
        timetableCalculationStatus: 'success',
        timetablePageError: '',
        timetablePageLoading: false,
        timetablePageLoadingDirection: '',
        timetablePageRequestId: 0,
        timetableSelectedIndex: 0,
        workspaceId: WORKSPACE_ID,
    }
}

function timetableCalculationContext(methods: Record<string, (...args: any[]) => unknown>) {
    const context: any = {
        timetableFilters: { include_saturday: true, free_days: null },
        currentStep: 'creation',
        planningMode: 'without_student',
        planningSelectionValues: {},
        saveState: vi.fn().mockResolvedValue(undefined),
        selectedCourseKeys: ['d1-a'],
        selectedModuleKeys: ['additional:D1'],
        selectedStudentCode: '',
        stateSaveFailed: false,
        timetableCalculationCheckedCombinationCount: 0,
        timetableCalculationCombinationCount: 0,
        timetableCalculationError: '',
        timetableCalculationProgressPercent: 0,
        timetableCalculationProgressPhase: 'preparing',
        timetableCalculationRequestId: 0,
        timetableCalculationResult: null,
        timetableCalculationStatus: 'idle',
        workspaceId: WORKSPACE_ID,
    }
    context.timetableCalculationPayload = () => methods.timetableCalculationPayload.call(context)
    context.timetableCalculationErrorMessage = (error: unknown) => (
        methods.timetableCalculationErrorMessage.call(context, error)
    )

    return context
}

describe('TimetableV3', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
        document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/'
    })

    it('offers both intuitive planning modes without a V2 shortcut', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        expect(source).toContain('StudentTimetableV3StateController')
        expect(source).toContain('showTimetableV3State.url(')
        expect(source).toContain('updateTimetableV3State.url()')
        expect(source).toContain('loadRobotStudents.url()')
        expect(source).toContain('StudentTimetableV3StudentInformationController')
        expect(source).toContain('loadV3StudentInformation.url({')
        expect(source).toContain('updateV3StudentSchoolLevel.url()')
        expect(source).not.toContain('/admin/students-timetables/timetable-v2/overview')
        expect(source).not.toContain('Version 2 öffnen')
        expect(source).toContain('Mit Studierendem')
        expect(source).toContain('Ohne Studierenden')
        expect(source).toContain('Wie möchten Sie beginnen?')
        expect(source).toContain('Studierenden auswählen')
        expect(source).toContain('Studierenden-Information')
        expect((TimetableV3 as any).name).toBe('TimetableV3')
    })

    it('opens the student dialog immediately when choosing with student', () => {
        const methods = (TimetableV3 as any).methods
        const context = {
            studentSearch: 'old search',
            studentOptionsError: true,
            studentDialogOpen: false,
            loadStudents: vi.fn(),
            focusStudentSearchField: vi.fn(),
            $nextTick: vi.fn(callback => callback()),
        }

        methods.chooseWithStudent.call(context)

        expect(context.studentDialogOpen).toBe(true)
        expect(context.studentSearch).toBe('')
        expect(context.focusStudentSearchField).toHaveBeenCalledOnce()
        expect(context.loadStudents).toHaveBeenCalledOnce()
    })

    it('uses enlarged typography throughout the student dialog', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )

        expect(source).toContain('timetable-v3__student-dialog-title')
        expect(source).toContain('font-size: 1.5rem')
        expect(source).toContain('timetable-v3__student-search :deep(.v-field__input)')
        expect(source).toContain('timetable-v3__student-list :deep(.v-list-item-title)')
        expect(source).toContain('font-size: 1.1rem')
    })

    it('shows the selected student or without-student choice prominently', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const studentInfoDialogStart = source.indexOf('<v-dialog v-model="studentInfoDialogOpen"')
        const studyInfoDialogStart = source.indexOf('<v-dialog v-model="studyInfoDialogOpen"')
        const studentSelectionDialogStart = source.indexOf('<v-dialog v-model="studentDialogOpen"')
        const studentInfoDialogSource = source.slice(studentInfoDialogStart, studyInfoDialogStart)
        const studyInfoDialogSource = source.slice(studyInfoDialogStart, studentSelectionDialogStart)

        expect(source).toContain('timetable-v3__selection-value')
        expect(source).toContain('timetable-v3__selection-icon')
        expect(source).toContain('timetable-v3__selection-label')
        expect(source).toContain("v-if=\"planningMode === 'with_student' && selectedStudentSexPresentation\"")
        expect(source).toContain('timetable-v3__student-sex-icon')
        expect(source).toContain('mdi-gender-male')
        expect(source).toContain('mdi-gender-female')
        expect(source).toContain('font-size: clamp(1.65rem, 3vw, 2.2rem)')
        expect(source).toContain('background: linear-gradient(135deg, #eef2ff 0%, #ffffff 52%, #f5f3ff 100%)')
        expect(source).toContain('0 14px 34px rgba(79, 70, 229, 0.16)')
        expect(source).toContain("{{ selectedStudentClass || '–' }} · {{ selectedStudentFullName || selectedStudentLabel }}")
        expect(source).toContain('class="timetable-v3__selection-religion"')
        expect(source).toContain('· {{ selectedStudentReligion }}')
        expect(source).toContain('<span v-else>Ohne Studierenden</span>')
        expect(source).toContain('v-if="selectedStudentEmail"')
        expect(source).toContain('@click="copySelectedStudentEmail"')
        expect(source).toContain('timetable-v3__email-row')
        expect(source).toContain('timetable-v3__email-copy-button')
        expect(source.match(/class="timetable-v3__student-data-fields"/g)).toHaveLength(2)
        expect(source.match(/class="timetable-v3__student-data-field"/g)).toHaveLength(4)
        expect(source.match(/v-if="planningMode === 'with_student' && \(selectedStudentSubjectPlanMismatch \|\| selectedStudentSchoolLevelMismatch\)"/g)).toHaveLength(2)
        expect(source.match(/v-if="selectedStudentSubjectPlanMismatch"/g)).toHaveLength(2)
        expect(source.match(/v-if="selectedStudentSchoolLevelMismatch"/g)).toHaveLength(2)
        expect(source).toContain('selectedStudentSubjectPlanMismatch')
        expect(source).toContain('selectedStudentSchoolLevelMismatch')
        expect(source).toContain('Stundentafel passt nicht zur Klasse')
        expect(source).toContain('Schulstufe ist für diese Studienform nicht gültig')
        expect(source.match(/@click="openSchoolLevelDialog"/g)).toHaveLength(3)
        expect(source).toContain('v-model="schoolLevelDialogOpen" max-width="640" persistent')
        expect(source).toContain('Ursprünglich gespeichert')
        expect(source).toContain('Gültige Schulstufen')
        expect(source).toContain('schoolLevelDialogOriginalValue')
        expect(source).toContain('selectedStudentSchoolLevelOptions')
        expect(source).toContain('@click="saveSchoolLevelDialog"')
        expect(source).toMatch(/\.timetable-v3__student-data-field--invalid \{[\s\S]*?border-color: #d92d20;/)
        expect(source).toMatch(/\.timetable-v3__school-level-option--original \{[\s\S]*?border-color: #f04438;/)
        expect(source).not.toContain('emailCopyStatusLabel')
        expect(source).not.toContain('timetable-v3__email-copy-status')
        expect(source).toContain('@click="openStudentInfoDialog"')
        expect(source).toContain('@click="openStudyInfoDialog"')
        expect(source).toContain('icon="mdi-school-outline"')
        expect(source).toContain('color="teal-darken-1"')
        expect(source).toContain('aria-label="Informationen zum Studium anzeigen"')
        expect(source).toContain('v-model="studentInfoDialogOpen" max-width="620" persistent')
        expect(source).toContain('v-model="studyInfoDialogOpen" max-width="960" persistent scrollable')
        expect(source).toContain('Studierenden-Information')
        expect(source).toContain('Informationen zum Studium')
        expect(studentInfoDialogSource).toContain("selectedStudentClass || '–'")
        expect(studentInfoDialogSource).toContain("selectedStudentFullName || '–'")
        expect(studentInfoDialogSource).toContain('Religion')
        expect(studentInfoDialogSource).toContain("selectedStudentReligion || '–'")
        expect(studentInfoDialogSource).toContain('Unterrichtsart')
        expect(studentInfoDialogSource).toContain('selectedStudentInstructionType')
        expect(studentInfoDialogSource).toContain('<span class="timetable-v3__info-value">{{ selectedStudentInstructionType }}</span>')
        expect(studentInfoDialogSource).toContain('Stundentafel')
        expect(studentInfoDialogSource).toContain('selectedStudentSubjectPlan')
        expect(studentInfoDialogSource).toContain('Semester')
        expect(studentInfoDialogSource).toContain('selectedStudentSemesterLabel')
        expect(studentInfoDialogSource).toContain('selectedStudentImportedSchoolLevel')
        expect(source.match(/class="timetable-v3__info-imported-school-level"/g)).toHaveLength(3)
        expect(source).toMatch(/\.timetable-v3__info-imported-school-level \{[\s\S]*?font-weight: 400;/)
        expect(studentInfoDialogSource).toContain('selectedStudentCalculationItems')
        expect(studentInfoDialogSource).toContain('Berechnung wird geladen')
        expect(studyInfoDialogSource).toContain('Informationen zum Studium')
        expect(studyInfoDialogSource).toContain('<v-card-text')
        expect(studyInfoDialogSource).toContain('v-for="group in studentStudyModuleGroups"')
        expect(studyInfoDialogSource).toContain('v-for="module in group.modules"')
        expect(studyInfoDialogSource).toContain('v-for="(grade, gradeIndex) in module.grades"')
        expect(studyInfoDialogSource).toContain('{{ module.code }}')
        expect(studyInfoDialogSource).toContain('{{ module.name }}')
        expect(studyInfoDialogSource).toContain('v-if="module.name && module.name !== module.code"')
        expect(studyInfoDialogSource).toContain('timetable-v3__study-module-name')
        expect(studyInfoDialogSource).toContain('{{ grade.value }}')
        expect(studyInfoDialogSource).toContain('timetable-v3__study-module-grade--${grade.status}')
        expect(studyInfoDialogSource).toContain('Keine Module')
        expect(studyInfoDialogSource).not.toContain('selectedStudentCalculationItems')
        expect(studyInfoDialogSource).not.toMatch(/module\.grade\s*===/)
        expect(source).toContain('timetable-v3__info-secondary-row')
        expect(source).toContain('timetable-v3__info-secondary-row--semester')
        expect(source).toContain('timetable-v3__info-primary-row')
        expect(source).toMatch(/\.timetable-v3__info-primary-row \{[\s\S]*?grid-template-columns: max-content max-content minmax\(0, 1fr\);[\s\S]*?column-gap: 32px;/)
        expect(source).toMatch(/\.timetable-v3__info-grid \{[\s\S]*?flex-wrap: wrap;/)
        expect(source).toMatch(/\.timetable-v3__info-secondary-row \{[\s\S]*?flex: 1 0 100%;/)
        expect(source).toMatch(/\.timetable-v3__info-value \{[\s\S]*?overflow: visible;[\s\S]*?text-overflow: clip;/)
        expect(source).toContain('.timetable-v3__info-primary-row > .timetable-v3__info-item + .timetable-v3__info-item::before')
        expect(source).toMatch(/\.timetable-v3__info-grid \{[\s\S]*?display: flex;/)
        expect(source).not.toContain('Ändern')
    })

    it('shows the student information on hover while preserving the click dialog', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )

        expect(source.match(/<v-menu\s+open-on-hover/g)).toHaveLength(4)
        expect(source.match(/:open-on-click="false"/g)).toHaveLength(4)
        expect(source.match(/#activator="\{ props \}"/g)).toHaveLength(4)
        expect(source.match(/v-bind="props"/g)).toHaveLength(4)
        expect(source.match(/@click="openStudentInfoDialog"/g)).toHaveLength(2)
        expect(source.match(/class="timetable-v3__info-hover-card"/g)).toHaveLength(2)
        expect(source.match(/v-for="item in selectedStudentHoverInformationItems"/g)).toHaveLength(2)
        expect(source).toContain('.timetable-v3__selection-value > .timetable-v3__selection-religion')
        expect(source).toContain('font-size: clamp(0.9rem, 1.5vw, 1.1rem)')
    })

    it('shows the study information on hover while preserving the click dialog', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )

        expect(source.match(/@click="openStudyInfoDialog"/g)).toHaveLength(2)
        expect(source.match(/class="timetable-v3__study-info-hover-card"/g)).toHaveLength(2)
        expect(source.match(/class="timetable-v3__study-info-hover-content pa-4 pt-2"/g)).toHaveLength(2)
        expect(source.match(/v-for="group in studentStudyModuleGroups"/g)).toHaveLength(3)
        expect(source.match(/location="start center"/g)).toHaveLength(2)
        expect(source.match(/:offset="16"/g)).toHaveLength(2)
        expect(source).toContain('.timetable-v3__study-info-hover-content')
        expect(source).toContain('max-height: min(68vh, 620px)')
    })

    it('opens and closes the persistent student information dialog', () => {
        const methods = (TimetableV3 as any).methods
        const context = {
            selectedStudent: { studentCode: '1001' },
            studentInfoDialogOpen: false,
            studyInfoDialogOpen: true,
            loadSelectedStudentSelection: vi.fn(),
        }

        methods.openStudentInfoDialog.call(context)
        expect(context.studentInfoDialogOpen).toBe(true)
        expect(context.studyInfoDialogOpen).toBe(false)
        expect(context.loadSelectedStudentSelection).toHaveBeenCalledOnce()

        methods.closeStudentInfoDialog.call(context)
        expect(context.studentInfoDialogOpen).toBe(false)
    })

    it('opens and closes the persistent study information dialog', () => {
        const methods = (TimetableV3 as any).methods
        const context = {
            selectedStudent: { studentCode: '1001' },
            studentInfoDialogOpen: true,
            studyInfoDialogOpen: false,
            loadSelectedStudentSelection: vi.fn(),
        }

        methods.openStudyInfoDialog.call(context)
        expect(context.studentInfoDialogOpen).toBe(false)
        expect(context.studyInfoDialogOpen).toBe(true)
        expect(context.loadSelectedStudentSelection).toHaveBeenCalledOnce()

        methods.closeStudyInfoDialog.call(context)
        expect(context.studyInfoDialogOpen).toBe(false)
    })

    it('displays the instruction type supplied by the backend without calculating it in the browser', () => {
        const methods = (TimetableV3 as any).methods
        const instructionType = (TimetableV3 as any).computed.selectedStudentInstructionType

        expect(instructionType.call({ selectedStudent: { instructionType: 'Kompaktunterricht' } })).toBe('Kompaktunterricht')
        expect(instructionType.call({ selectedStudent: { instruction_type: 'Normalunterricht' } })).toBe('Normalunterricht')
        expect(methods.studentClassIsKompaktunterricht).toBeUndefined()
    })

    it('displays the subject plan supplied by the backend', () => {
        const subjectPlan = (TimetableV3 as any).computed.selectedStudentSubjectPlan
        const subjectPlanMismatch = (TimetableV3 as any).computed.selectedStudentSubjectPlanMismatch

        expect(subjectPlan.call({ selectedStudent: { subjectPlan: 'AHS-KS-ALLE' } })).toBe('AHS-KS-ALLE')
        expect(subjectPlan.call({ selectedStudent: { subject_plan: 'AHS-ALLE' } })).toBe('AHS-ALLE')
        expect(subjectPlan.call({ selectedStudent: {} })).toBe('–')
        expect(subjectPlanMismatch.call({ selectedStudent: { subjectPlanMismatch: true } })).toBe(true)
        expect(subjectPlanMismatch.call({ selectedStudent: { subject_plan_mismatch: true } })).toBe(true)
        expect(subjectPlanMismatch.call({ selectedStudent: {} })).toBe(false)
    })

    it('shows only imported male and female sex values with the matching icon color', () => {
        const methods = (TimetableV3 as any).methods
        const presentation = (TimetableV3 as any).computed.selectedStudentSexPresentation

        expect(methods.normalizedStudentSex({ sex: ' M ' })).toBe('m')
        expect(methods.normalizedStudentSex({ sex: 'W' })).toBe('w')
        expect(presentation.call({ selectedStudentSex: 'm' })).toEqual({
            icon: 'mdi-gender-male',
            color: 'blue',
            label: 'männlich',
        })
        expect(presentation.call({ selectedStudentSex: 'w' })).toEqual({
            icon: 'mdi-gender-female',
            color: 'pink',
            label: 'weiblich',
        })
        expect(presentation.call({ selectedStudentSex: '' })).toBeNull()
    })

    it('hydrates imported details for an older persisted student selection', async () => {
        const methods = (TimetableV3 as any).methods
        const context = {
            selectedStudent: { studentCode: '1001', firstName: 'Mia' },
            students: [{
                student_code: '1001',
                sex: 'w',
                religion: 'Rk',
                instruction_type: 'Kompaktunterricht',
                semester: 5,
                school_level: '10_2',
            }],
            loadStudents: vi.fn().mockResolvedValue(undefined),
            normalizedStudentSex: methods.normalizedStudentSex,
            normalizedStudentSemester: methods.normalizedStudentSemester,
            saveState: vi.fn().mockResolvedValue(undefined),
        }

        await methods.hydrateSelectedStudentDetails.call(context)

        expect(context.selectedStudent).toEqual({
            studentCode: '1001',
            firstName: 'Mia',
            sex: 'w',
            religion: 'Rk',
            instructionType: 'Kompaktunterricht',
            semester: 5,
            schoolLevel: '10_2',
        })
        expect(context.saveState).toHaveBeenCalledOnce()
    })

    it('shows the calculated semester in the student information dialog', () => {
        const methods = (TimetableV3 as any).methods
        const semesterLabel = (TimetableV3 as any).computed.selectedStudentSemesterLabel
        const importedSchoolLevel = (TimetableV3 as any).computed.selectedStudentImportedSchoolLevel
        const schoolLevelMismatch = (TimetableV3 as any).computed.selectedStudentSchoolLevelMismatch

        expect(methods.normalizedStudentSemester({ semester: '5' })).toBe(5)
        expect(methods.normalizedStudentSemester({ semester: null })).toBeNull()
        expect(semesterLabel.call({
            selectedStudent: { semester: 5 },
            studentSelectionItems: [],
            normalizedStudentSemester: methods.normalizedStudentSemester,
        })).toBe('5. Semester')
        expect(importedSchoolLevel.call({ selectedStudent: { schoolLevel: '10_2' } })).toBe('10_2')
        expect(importedSchoolLevel.call({ selectedStudent: { school_level: '11_1' } })).toBe('11_1')
        expect(importedSchoolLevel.call({ selectedStudent: {} })).toBe('–')
        expect(schoolLevelMismatch.call({ selectedStudent: { schoolLevelMismatch: true } })).toBe(true)
        expect(schoolLevelMismatch.call({ selectedStudent: { school_level_mismatch: true } })).toBe(true)
        expect(schoolLevelMismatch.call({ selectedStudent: {} })).toBe(false)
    })

    it('opens the persistent school-level correction and keeps the original value selectable', () => {
        const methods = (TimetableV3 as any).methods
        const canSave = (TimetableV3 as any).computed.canSaveSchoolLevelDialog
        const context = {
            selectedStudent: { studentCode: '1001' },
            selectedStudentSchoolLevelMismatch: true,
            selectedStudentOriginalSchoolLevel: '',
            selectedStudentImportedSchoolLevel: '10_1',
            studentInfoDialogOpen: true,
            studyInfoDialogOpen: false,
            schoolLevelDialogOpen: false,
            schoolLevelDialogSelection: '',
            schoolLevelDialogOriginalValue: '',
            schoolLevelDialogSaving: false,
            schoolLevelDialogError: 'old error',
        }

        methods.openSchoolLevelDialog.call(context)

        expect(context.studentInfoDialogOpen).toBe(false)
        expect(context.schoolLevelDialogOpen).toBe(true)
        expect(context.schoolLevelDialogOriginalValue).toBe('10_1')
        expect(context.schoolLevelDialogSelection).toBe('10_1')
        expect(context.schoolLevelDialogError).toBe('')
        expect(canSave.call({
            schoolLevelDialogSelection: '10_1',
            schoolLevelDialogOriginalValue: '10_1',
            schoolLevelDialogSaving: false,
            selectedStudentImportedSchoolLevel: '11_1',
            selectedStudentSchoolLevelOptions: [{ value: '11_1', semester: 3 }],
        })).toBe(true)
    })

    it('saves a selected school level through Wayfinder and applies the response immediately', async () => {
        const methods = (TimetableV3 as any).methods
        const studentInformation = {
            school_level: '11_1',
            school_level_mismatch: false,
            original_school_level: '10_1',
        }
        const put = vi.fn().mockResolvedValue({ data: { data: studentInformation } })
        vi.stubGlobal('axios', { put })
        const context = {
            canSaveSchoolLevelDialog: true,
            selectedStudentCode: '1001',
            schoolLevelDialogSelection: '11_1',
            schoolLevelDialogOriginalValue: '10_1',
            schoolLevelDialogSaving: false,
            schoolLevelDialogError: '',
            schoolLevelDialogOpen: true,
            planningSelectionForRequest: vi.fn().mockReturnValue({ religion: 'ETH' }),
            applySelectedStudentInformation: vi.fn(),
            resetTimetableCalculation: vi.fn(),
            saveState: vi.fn().mockResolvedValue(undefined),
        }

        await methods.saveSchoolLevelDialog.call(context)

        expect(put).toHaveBeenCalledWith(
            '/api/admin/students-timetables/timetable-v3/student-information/school-level',
            {
                student_code: '1001',
                school_level: '11_1',
                selection: { religion: 'ETH' },
            },
        )
        expect(context.applySelectedStudentInformation).toHaveBeenCalledWith(studentInformation, '1001')
        expect(context.resetTimetableCalculation).toHaveBeenCalledOnce()
        expect(context.saveState).toHaveBeenCalledOnce()
        expect(context.schoolLevelDialogOpen).toBe(false)
        expect(context.schoolLevelDialogSaving).toBe(false)
    })

    it('loads configured school-hour time ranges for the empty manual timetable once', async () => {
        const methods = (TimetableV3 as any).methods
        const currentStepWatcher = (TimetableV3 as any).watch.currentStep
        const get = vi.fn().mockResolvedValue({
            data: {
                data: [
                    { hour: 7, from: '14:45', until: '15:30' },
                    { hour: 8, from: '15:30', until: '16:15' },
                ],
            },
        })
        vi.stubGlobal('axios', { get })
        const context = {
            isLoadingState: true,
            isManualTimetableAdoption: true,
            schoolHours: [],
            schoolHoursLoaded: false,
            schoolHoursLoading: false,
            loadSchoolHours: vi.fn(),
        }

        currentStepWatcher.call(context, 'adoption', 'modules')

        expect(context.loadSchoolHours).toHaveBeenCalledOnce()

        await methods.loadSchoolHours.call(context)
        await methods.loadSchoolHours.call(context)

        expect(get).toHaveBeenCalledOnce()
        expect(get).toHaveBeenCalledWith('/api/admin/students-timetables/school-hours')
        expect(context.schoolHours).toEqual([
            { hour: 7, from: '14:45', until: '15:30' },
            { hour: 8, from: '15:30', until: '16:15' },
        ])
        expect(context.schoolHoursLoaded).toBe(true)
        expect(context.schoolHoursLoading).toBe(false)
    })

    it('loads and presents the calculated student selections from the shared overview', async () => {
        const methods = (TimetableV3 as any).methods
        const calculationItems = (TimetableV3 as any).computed.selectedStudentCalculationItems
        const selectionItems = [
            { key: 'religion', label: 'Ethik / Religion', value: 'ETH - Ethik' },
            { key: 'language', label: 'Sprache', value: 'L - Latein' },
            { key: 'branch', label: 'Zweig', value: 'Gymnasialer Zweig' },
            { key: 'arts_subject', label: 'ME / BE', value: 'BE - Bildnerische Erziehung' },
        ]
        const selectionFields = [
            {
                key: 'religion',
                label: 'Ethik / Religion',
                selected_value: 'ETH',
                options: [
                    { title: 'ETH - Ethik', value: 'ETH' },
                    { title: 'Rk - Religion katholisch', value: 'Rk' },
                ],
            },
            {
                key: 'language',
                label: 'Sprache',
                selected_value: 'L',
                options: [{ title: 'L - Latein', value: 'L' }],
            },
            {
                key: 'branch',
                label: 'Zweig',
                selected_value: 'gymnasial',
                options: [{ title: 'Gymnasialer Zweig', value: 'gymnasial' }],
            },
            {
                key: 'arts_subject',
                label: 'ME / BE',
                selected_value: 'BE',
                options: [{ title: 'BE - Bildnerische Erziehung', value: 'BE' }],
            },
        ]
        const moduleGroups = [
            {
                key: 'exempt',
                label: 'Befreite Module',
                count: 1,
                modules: [{
                    code: 'BU1',
                    name: 'Buchhaltung 1',
                    grade: 'B',
                    grades: [{ value: 'B', status: 'exempt' }],
                }],
            },
            {
                key: 'passed',
                label: 'Bestandene Module',
                count: 1,
                modules: [{
                    code: 'D1',
                    name: 'Deutsch 1',
                    grade: '2',
                    grades: [
                        { value: '2', status: 'passed' },
                        { value: 'N', status: 'failed' },
                        { value: 'N', status: 'failed' },
                        { value: '5', status: 'failed' },
                    ],
                }],
            },
            {
                key: 'failed',
                label: 'Nicht bestandene Module',
                count: 1,
                modules: [{
                    code: 'M1',
                    name: 'Mathematik 1',
                    grade: 'N',
                    grades: [{ value: 'N', status: 'failed' }],
                }],
            },
        ]
        const moduleSelectionGroups = [
            {
                key: 'finished',
                label: 'Abgeschlossene',
                description: 'Bereits befreit oder bestanden',
                count: 1,
                modules: [{
                    selection_key: 'finished:BU1',
                    code: 'BU1',
                    name: 'Buchhaltung 1',
                    status_label: 'Befreit',
                    selected_by_default: false,
                    grades: ['B'],
                }],
            },
            {
                key: 'current',
                label: 'Aktuelle',
                description: 'Für das aktuelle Semester',
                count: 1,
                modules: [{
                    selection_key: 'current:D5',
                    code: 'D5',
                    name: 'Deutsch 5',
                    semester_label: '5. Semester',
                    selected_by_default: true,
                    grades: [],
                }],
            },
        ]
        const mainModuleSelectionGroups = [
            {
                key: 'BU',
                code: 'BU',
                name: 'Biologie und Umweltkunde',
                description: '2 Module verfügbar',
                count: 2,
                modules: [
                    { selection_key: 'additional:BU1', code: 'BU1', name: 'Biologie 1' },
                    { selection_key: 'additional:BU2', code: 'BU2', name: 'Biologie 2' },
                ],
            },
        ]
        const get = vi.fn().mockResolvedValue({
            data: {
                data: {
                    religion: 'Rk',
                    instruction_type: 'Kompaktunterricht',
                    subject_plan: 'AHS-KS-ALLE',
                    subject_plan_mismatch: true,
                    school_level: '10_1',
                    school_level_mismatch: true,
                    original_school_level: '',
                    school_level_options: [{ value: '11_1', semester: 3 }],
                    semester: 5,
                    items: selectionItems,
                    selection_fields: selectionFields,
                    module_groups: moduleGroups,
                    module_selection_groups: moduleSelectionGroups,
                    main_module_selection_groups: mainModuleSelectionGroups,
                },
            },
        })
        vi.stubGlobal('axios', { get })
        const context = {
            planningMode: 'with_student',
            selectedStudentCode: '1001',
            selectedStudent: { studentCode: '1001' },
            studentSelectionDetailsLoading: false,
            studentSelectionDetailsError: false,
            studentSelectionDetailsCode: '',
            studentSelectionDetailsRequestCode: '',
            studentSelectionItems: [],
            studentStudyModuleGroups: [],
            moduleSelectionGroups: [],
            mainModuleSelectionGroups: [],
            manualModuleCatalogView: 'student',
            selectedModuleKeys: [],
            selectedCourseKeys: [],
            activeModuleGroupKey: '',
            moduleSelectionResetPending: false,
            planningSelectionFields: [],
            planningSelectionValues: {},
            storedState: null,
            normalizedStudentSemester: methods.normalizedStudentSemester,
            resetSelectedStudentSelectionDetails: methods.resetSelectedStudentSelectionDetails,
            planningSelectionForRequest: methods.planningSelectionForRequest,
            applySelectedStudentInformation: methods.applySelectedStudentInformation,
            setPlanningSelectionFields: methods.setPlanningSelectionFields,
            setModuleSelectionGroups: methods.setModuleSelectionGroups,
            setMainModuleSelectionGroups: methods.setMainModuleSelectionGroups,
        }

        await methods.loadSelectedStudentSelection.call(context)

        expect(get).toHaveBeenCalledWith(
            '/api/admin/students-timetables/timetable-v3/student-information?student_code=1001',
        )
        expect(context.studentSelectionItems).toEqual(selectionItems)
        expect(context.studentStudyModuleGroups).toEqual(moduleGroups)
        expect(context.studentStudyModuleGroups[1].modules[0].name).toBe('Deutsch 1')
        expect(context.moduleSelectionGroups).toEqual(moduleSelectionGroups)
        expect(context.mainModuleSelectionGroups).toEqual(mainModuleSelectionGroups)
        expect(context.selectedModuleKeys).toEqual([])
        expect(context.selectedCourseKeys).toEqual([])
        expect(context.activeModuleGroupKey).toBe('')
        expect(context.planningSelectionFields).toEqual(selectionFields)
        expect(context.planningSelectionValues).toEqual({
            religion: 'ETH',
            language: 'L',
            branch: 'gymnasial',
            arts_subject: 'BE',
        })
        expect(context.selectedStudent).toEqual({
            studentCode: '1001',
            religion: 'Rk',
            instructionType: 'Kompaktunterricht',
            subjectPlan: 'AHS-KS-ALLE',
            subjectPlanMismatch: true,
            schoolLevel: '10_1',
            schoolLevelMismatch: true,
            originalSchoolLevel: '',
            schoolLevelOptions: [{ value: '11_1', semester: 3 }],
            semester: 5,
        })
        expect(context.studentSelectionDetailsCode).toBe('1001')
        expect(context.studentSelectionDetailsLoading).toBe(false)
        expect(calculationItems.call({ studentSelectionItems: selectionItems })).toEqual(selectionItems)
    })

    it('renders and persists only the backend-provided study selections', async () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const methods = (TimetableV3 as any).methods
        const compactPlanningSelectionItems = (TimetableV3 as any).computed.compactPlanningSelectionItems
        const saveState = vi.fn().mockResolvedValue(undefined)
        const loadSelectedStudentSelection = vi.fn().mockResolvedValue(undefined)
        const context = {
            planningSelectionFields: [{
                key: 'language',
                label: 'Sprache',
                selected_value: 'L',
                options: [
                    { title: 'L - Latein', value: 'L' },
                    { title: 'F - Französisch', value: 'F' },
                ],
            }],
            planningSelectionValues: { language: 'L' },
            studentSelectionDetailsCode: '1001',
            moduleSelectionResetPending: false,
            saveState,
            loadSelectedStudentSelection,
        }

        await methods.updatePlanningSelection.call(context, 'language', 'F')

        expect(context.planningSelectionValues).toEqual({ language: 'F' })
        expect(saveState).toHaveBeenCalledTimes(2)
        expect(loadSelectedStudentSelection).toHaveBeenCalledOnce()
        expect(compactPlanningSelectionItems.call(context)).toEqual([{
            key: 'language',
            label: 'Sprache',
            value: 'F - Französisch',
        }])

        await methods.updatePlanningSelection.call(context, 'language', 'F')

        expect(context.planningSelectionValues).toEqual({ language: null })
        expect(saveState).toHaveBeenCalledTimes(4)
        expect(loadSelectedStudentSelection).toHaveBeenCalledTimes(2)
        expect(compactPlanningSelectionItems.call(context)).toEqual([{
            key: 'language',
            label: 'Sprache',
            value: '–',
        }])
        expect(methods.planningSelectionForRequest.call({
            planningSelectionValues: { language: null },
            storedState: null,
        }, '1001')).toEqual({ language: '' })

        await methods.updatePlanningSelection.call(context, 'language', 'invented')

        expect(context.planningSelectionValues).toEqual({ language: null })
        expect(saveState).toHaveBeenCalledTimes(4)
        expect(source).toContain('Studienauswahl')
        expect(source).toContain('Erneut anklicken, um eine Auswahl abzuwählen.')
        expect(source).toContain('v-for="field in planningSelectionFields"')
        expect(source).toContain('v-for="option in field.options"')
        expect(source).toContain('{{ field.label }}')
        expect(source).toContain('{{ option.title }}')
        expect(source).toContain('@click="updatePlanningSelection(field.key, option.value)"')
        expect(source).toContain('timetable-v3__planning-selection-option--selected')
        expect(source).toContain('v-if="planningSelectionFields.length && !studentSelectionDetailsError"')
        expect(source).toContain(':disabled="studentSelectionDetailsLoading || isSavingState"')
        expect(source).toMatch(
            /<v-card[\s\S]*?v-if="hasPlanningSelectionContext"[\s\S]*?class="timetable-v3__planning-selection mt-6 mx-auto"[\s\S]*?tag="section"[\s\S]*?max-width="960"[\s\S]*?width="100%"/,
        )
        expect(source).not.toContain('<v-select')
        expect(source).toContain('Ethik / Religion')
        expect(source).toContain('Sprache')
        expect(source).toContain('Zweig')
        expect(source).toContain('ME / BE')
        expect(source).not.toContain("field.key === 'religion'")
    })

    it('copies the selected student email with one click', async () => {
        const methods = (TimetableV3 as any).methods
        const writeText = vi.fn().mockResolvedValue(undefined)
        vi.stubGlobal('navigator', { clipboard: { writeText } })
        const context = {
            selectedStudentEmail: 'mia@example.test',
            emailCopyStatus: 'idle',
        }

        await methods.copySelectedStudentEmail.call(context)

        expect(writeText).toHaveBeenCalledWith('mia@example.test')
        expect(context.emailCopyStatus).toBe('copied')
    })

    it('shows the shared planning selection immediately on the entry page', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const hasPlanningSelectionContext = (TimetableV3 as any).computed.hasPlanningSelectionContext

        const restartButtonStart = source.indexOf('class="timetable-v3__restart-button"')
        const restartButtonEnd = source.indexOf('</v-btn>', restartButtonStart)
        const restartButtonSource = source.slice(restartButtonStart, restartButtonEnd)
        const continueButtonStart = source.indexOf('class="timetable-v3__selection-continue-button"')
        const continueButtonEnd = source.indexOf('</v-btn>', continueButtonStart)
        const continueButtonSource = source.slice(continueButtonStart, continueButtonEnd)
        const selectionStepSource = source.slice(0, source.indexOf('<div v-else'))

        expect(hasPlanningSelectionContext.call({ planningMode: null, selectedStudentCode: '' })).toBe(false)
        expect(hasPlanningSelectionContext.call({ planningMode: 'with_student', selectedStudentCode: '' })).toBe(false)
        expect(hasPlanningSelectionContext.call({ planningMode: 'with_student', selectedStudentCode: '1001' })).toBe(true)
        expect(hasPlanningSelectionContext.call({ planningMode: 'without_student', selectedStudentCode: '' })).toBe(true)
        expect(source).toContain('v-if="hasPlanningSelectionContext"')
        expect(restartButtonSource).toContain(':disabled="!hasPlanningSelectionContext || studentSelectionDetailsLoading || isLoadingState || isSavingState"')
        expect(continueButtonSource).toContain(':disabled="!hasPlanningSelectionContext || studentSelectionDetailsLoading || isLoadingState || isSavingState"')
        expect(source).toContain("currentStep === 'selection'")
        expect(source).toContain('@click="continueToNextStep"')
        expect(source).not.toContain('continueToCreationModeStep')
        expect(source).not.toContain('returnToEntryStep')
        expect(source).not.toContain('Wie möchten Sie den Stundenplan erstellen?')
        expect(selectionStepSource).not.toContain('Automatik')
        expect(selectionStepSource).not.toContain('Manuell')
        expect(selectionStepSource).not.toContain('scheduleCreationMode')
        expect(selectionStepSource).not.toContain('chooseCreationMode')
        expect(source).toContain('timetable-v3__selection-value')
        expect(source).toContain('Studienauswahl')
        expect(source).toContain('font-size: clamp(1.65rem, 3vw, 2.2rem)')
        expect(continueButtonSource).toContain('Weiter')
        expect(continueButtonSource).toContain('@click="continueToNextStep"')
        expect(source).toContain('timetable-v3__page-actions')
    })

    it('numbers V3 pages according to the active planning branch', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const currentPageLabel = (TimetableV3 as any).computed.currentPageLabel

        expect(currentPageLabel.call({ currentStep: 'selection', scheduleCreationMode: null })).toBe('1')
        expect(currentPageLabel.call({ currentStep: 'modules', scheduleCreationMode: null })).toBe('2')
        expect(currentPageLabel.call({ currentStep: 'modules', scheduleCreationMode: 'automatic' })).toBe('2A')
        expect(currentPageLabel.call({ currentStep: 'creation', scheduleCreationMode: 'automatic' })).toBe('2B')
        expect(currentPageLabel.call({ currentStep: 'adoption', isManualTimetableAdoption: true })).toBe('3B')
        expect(currentPageLabel.call({ currentStep: 'adoption', isManualTimetableAdoption: false })).toBe('3A')
        expect(currentPageLabel.call({ currentStep: 'unknown', scheduleCreationMode: null })).toBe('')
        expect(source.match(/class="timetable-v3__page-number text-overline text-primary"/g)).toHaveLength(2)
        expect(source.match(/v-if="currentPageLabel"/g)).toHaveLength(2)
        expect(source.match(/Seite \{\{ currentPageLabel \}\}/g)).toHaveLength(2)
        expect(source).toMatch(/\.timetable-v3__page-header\s*\{[\s\S]*?justify-content:\s*space-between;/)
        expect(source).toMatch(/\.timetable-v3__page-number\s*\{[\s\S]*?margin-left:\s*auto;/)
    })

    it('opens a minimal next page with the complete current selection card and neutral navigation', async () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const methods = (TimetableV3 as any).methods
        const currentStep = (TimetableV3 as any).computed.currentStep
        const push = vi.fn()
        const context = {
            hasPlanningSelectionContext: false,
            workspaceId: WORKSPACE_ID,
            planningMode: 'with_student',
            selectedStudentCode: '1001',
            $router: { push },
        }

        methods.continueToNextStep.call(context)
        expect(push).not.toHaveBeenCalled()

        context.hasPlanningSelectionContext = true
        methods.continueToNextStep.call(context)
        expect(push).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v3/modules',
            query: {
                workspace_id: WORKSPACE_ID,
                planning_mode: 'with_student',
                student_code: '1001',
            },
        })

        methods.returnToSelectionStep.call(context)
        expect(push).toHaveBeenLastCalledWith({
            path: '/admin/students-timetables/timetable-v3/overview',
            query: {
                workspace_id: WORKSPACE_ID,
                planning_mode: 'with_student',
                student_code: '1001',
            },
        })
        expect(currentStep.call({ $route: { params: { subsection: 'overview' } } })).toBe('selection')
        expect(currentStep.call({ $route: { params: { subsection: 'modules' } } })).toBe('modules')
        expect(currentStep.call({ $route: { params: { subsection: 'creation' } } })).toBe('creation')
        expect(currentStep.call({ $route: { params: { subsection: 'adoption' } } })).toBe('adoption')

        const nextStepStart = source.indexOf('<div v-else class="timetable-v3__step timetable-v3__next-step">')
        const nextStepEnd = source.indexOf('<v-dialog', nextStepStart)
        const nextStepSource = source.slice(nextStepStart, nextStepEnd)
        const backButtonStart = nextStepSource.indexOf('class="timetable-v3__back-button"')
        const backButtonEnd = nextStepSource.indexOf('</v-btn>', backButtonStart)
        const backButtonSource = nextStepSource.slice(backButtonStart, backButtonEnd)

        expect(nextStepSource).toContain('Aktuelle Auswahl')
        expect(nextStepSource).toContain("v-if=\"planningMode === 'with_student'\"")
        expect(nextStepSource).toContain("{{ selectedStudentClass || '–' }} · {{ selectedStudentFullName || selectedStudentLabel }}")
        expect(nextStepSource).toContain('timetable-v3__selection-religion')
        expect(nextStepSource).toContain("planningMode === 'with_student' && selectedStudentSexPresentation")
        expect(nextStepSource).toContain('Ohne Studierenden')
        expect(nextStepSource).toContain('timetable-v3__selection-summary')
        expect(nextStepSource).toContain('selectedStudentEmail')
        expect(nextStepSource).toContain('@click="copySelectedStudentEmail"')
        expect(nextStepSource).toContain('@click="openStudentInfoDialog"')
        expect(nextStepSource).toContain('@click="openStudyInfoDialog"')
        expect(nextStepSource).toContain('timetable-v3__compact-planning-card')
        expect(nextStepSource).toContain('v-for="item in compactPlanningSelectionItems"')
        expect(nextStepSource).toContain('{{ item.label }}')
        expect(nextStepSource).toContain('{{ item.value }}')
        expect(nextStepSource).toContain("v-if=\"currentStep === 'modules' || currentStep === 'adoption'\"")
        expect(nextStepSource).toContain('timetable-v3__compact-planning-title')
        expect(nextStepSource).toContain('Studienauswahl')
        expect(nextStepSource).toContain('Soll der Stundenplan automatisch oder manuell erzeugt werden?')
        expect(nextStepSource).toContain('Welche Module sollen zur Stundenplanerstellung berücksichtigt werden?')
        expect(nextStepSource).toContain('Automatischer Stundenplan')
        expect(nextStepSource).toContain('Manueller Stundenplan')
        expect(nextStepSource).toContain('chooseScheduleCreationMode')
        expect(nextStepSource).toContain('v-for="group in moduleSelectionGroups"')
        expect(nextStepSource).toContain('Ausgewählte Module')
        expect(nextStepSource).toContain('class="timetable-v3__module-tile"')
        expect(nextStepSource).toContain('@click="openModuleCoursesDialog(module)"')
        expect(nextStepSource).not.toContain('role="tab"')
        expect(nextStepSource).toContain('class="timetable-v3__back-button"')
        expect(nextStepSource).toContain('prepend-icon="mdi-arrow-left"')
        expect(nextStepSource).toContain('@click="returnToSelectionStep"')
        expect(nextStepSource).toContain('Zurück')
        expect(backButtonSource).toContain(':disabled="studentSelectionDetailsLoading || isLoadingState || isSavingState"')
        expect(nextStepSource).not.toContain('class="timetable-v3__next-continue-button"')
    })

    it('starts without a timetable mode and only shows modules for automatic creation', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const methods = (TimetableV3 as any).methods
        const currentStepWatcher = (TimetableV3 as any).watch.currentStep
        const data = (TimetableV3 as any).data()
        const context = {
            scheduleCreationMode: null,
            saveState: vi.fn().mockResolvedValue(undefined),
            ensureValidCurrentStep: vi.fn(),
            isLoadingState: false,
        }
        const questionPosition = source.indexOf('Soll der Stundenplan automatisch oder manuell erzeugt werden?')
        const modeCardsPosition = source.indexOf('class="timetable-v3__schedule-mode-options"')
        const automaticCardPosition = source.indexOf('timetable-v3__schedule-mode-card--automatic')
        const automaticCardEndPosition = source.indexOf('</label>', automaticCardPosition)
        const selectedModulesPosition = source.indexOf('timetable-v3__schedule-mode-selected-modules')
        const manualCardPosition = source.indexOf('timetable-v3__schedule-mode-card--manual')
        const manualCardEndPosition = source.indexOf('</button>', manualCardPosition)
        const availableModulesPosition = source.indexOf('timetable-v3__schedule-mode-available-modules')
        const automaticCardSource = source.slice(automaticCardPosition, automaticCardEndPosition)
        const manualCardSource = source.slice(manualCardPosition, manualCardEndPosition)

        expect(questionPosition).toBeGreaterThan(-1)
        expect(modeCardsPosition).toBeGreaterThan(questionPosition)
        expect(source).toContain("scheduleCreationMode === 'automatic' ? 'Modulauswahl' : 'Stundenplanerstellung'")
        expect(source).toContain("scheduleCreationMode === 'automatic'")
        expect(source).toContain('Welche Module sollen zur Stundenplanerstellung berücksichtigt werden?')
        expect(selectedModulesPosition).toBeGreaterThan(automaticCardPosition)
        expect(selectedModulesPosition).toBeLessThan(automaticCardEndPosition)
        expect(availableModulesPosition).toBeGreaterThan(manualCardPosition)
        expect(availableModulesPosition).toBeLessThan(manualCardEndPosition)
        expect(automaticCardSource).toContain('v-if="scheduleCreationMode === \'automatic\'"')
        expect(automaticCardSource).toContain('class="timetable-v3__schedule-create-action"')
        expect(automaticCardSource).toContain('class="timetable-v3__deselect-all-modules-button"')
        expect(automaticCardSource).toMatch(/class="timetable-v3__deselect-all-modules-button"[\s\S]*?color="error"/)
        expect(automaticCardSource).toContain('@click.prevent.stop="deselectAllSelectedModules"')
        expect(automaticCardSource).toContain('Alle abwählen')
        expect(automaticCardSource).toContain('class="timetable-v3__schedule-create-button"')
        expect(automaticCardSource).toContain('v-if="selectedModuleCount > 0"')
        expect(automaticCardSource).toContain('Stundenplan erstellen')
        expect(automaticCardSource).toContain('prepend-icon="mdi-calendar-check"')
        expect(automaticCardSource).toContain('append-icon="mdi-arrow-right"')
        expect(automaticCardSource).toContain('elevation="8"')
        expect(automaticCardSource).toContain('height="52"')
        expect(automaticCardSource).toContain('size="large"')
        expect(automaticCardSource).toContain('@click.prevent.stop="openTimetableCreationPage"')
        expect(automaticCardSource).not.toContain('@click="createTimetable"')
        expect(manualCardSource).toContain('v-if="scheduleCreationMode === \'manual\'"')
        expect(manualCardSource).toContain('@click="openManualTimetablePage"')
        expect(manualCardSource).not.toContain('@change="chooseScheduleCreationMode(\'manual\')"')
        expect(manualCardSource).toContain('type="button"')
        expect(manualCardSource).toContain('Manuell öffnen')
        expect(source).toContain('Verfügbare Module und Unterrichte')
        expect(source).toContain('Alle Module und Unterrichte stehen zur Verfügung.')
        expect(source).toContain(':aria-busy="timetablePageLoading ? \'true\' : \'false\'"')
        expect(source).toContain(':inert="timetablePageLoading"')
        expect(source).toContain('Sie wählen die Module. Das System erstellt und optimiert daraus den Stundenplan.')
        expect(source).toContain('class="timetable-v3__schedule-mode-recommendation"')
        expect(source).toContain('mdi-star-four-points')
        expect(source).toContain('Empfohlen')
        expect(source).toMatch(
            /\.timetable-v3__schedule-mode-kickers\s*\{[\s\S]*?justify-content:\s*flex-end;/,
        )
        expect(automaticCardSource).not.toContain(
            '<span class="timetable-v3__schedule-mode-label">Automatisch</span>',
        )
        expect(manualCardSource).not.toContain('<span class="timetable-v3__schedule-mode-label">Manuell</span>')
        expect(source).toContain('background: linear-gradient(135deg, #fef08a, #facc15)')
        expect(source).toContain(
            'Sie stellen den Stundenplan selbst zusammen und platzieren die Unterrichte manuell.',
        )
        expect(source).toContain("scheduleCreationMode === 'automatic' ? 'Ausgewählt' : 'Automatisch wählen'")
        expect(source).not.toContain("scheduleCreationMode === 'manual' ? 'Ausgewählt' : 'Manuell wählen'")
        expect(source).toContain('role="group"')
        expect(source).toContain('type="radio"')
        expect(source).toContain('name="timetable-v3-schedule-mode"')
        expect(source).toContain(':checked="scheduleCreationMode === \'automatic\'"')
        expect(source).not.toContain(':checked="scheduleCreationMode === \'manual\'"')
        expect(source).toContain('grid-template-columns: repeat(2, minmax(0, 1fr))')
        expect(source).toContain(
            "'timetable-v3__schedule-mode-options--automatic-selected': scheduleCreationMode === 'automatic'",
        )
        expect(source).toContain(
            "'timetable-v3__schedule-mode-options--manual-selected': scheduleCreationMode === 'manual'",
        )
        expect(source).toContain('grid-template-columns: minmax(0, 2fr) minmax(0, 1fr)')
        expect(source).toContain('grid-template-columns: minmax(0, 1fr) minmax(0, 2fr)')
        expect(source).toContain('.timetable-v3__schedule-mode-card--selected .timetable-v3__schedule-mode-icon')
        expect(source).toContain('.timetable-v3__schedule-mode-card--selected .timetable-v3__schedule-mode-status')
        expect(source).toContain('min-height: 220px')
        expect(source).toContain('width: 58px')
        expect(source).toContain('background: var(--schedule-mode-accent)')
        expect(source).toMatch(/\.timetable-v3__schedule-create-action\s*\{[\s\S]*?justify-content: space-between;/)
        expect(source).toMatch(/\.timetable-v3__schedule-create-button\s*\{[\s\S]*?linear-gradient\(135deg, #4338ca, #6366f1\);/)
        expect(source).toMatch(/\.timetable-v3__schedule-create-button:hover,[\s\S]*?transform: translateY\(-2px\) scale\(1\.01\);/)
        expect(source.match(/v-if="scheduleCreationMode === 'automatic'"/g)).toHaveLength(4)
        expect(source).toContain(`v-if="scheduleCreationMode === 'automatic' && usesMainModuleGroups"`)
        expect(source).toContain('scheduleCreationMode: this.scheduleCreationMode')
        expect(data.scheduleCreationMode).toBeNull()

        methods.chooseScheduleCreationMode.call(context, 'unsupported')
        expect(context.scheduleCreationMode).toBeNull()

        methods.chooseScheduleCreationMode.call(context, 'automatic')
        expect(context.scheduleCreationMode).toBe('automatic')

        methods.chooseScheduleCreationMode.call(context, 'manual')
        expect(context.scheduleCreationMode).toBe('manual')
        expect(context.saveState).toHaveBeenCalledTimes(2)

        currentStepWatcher.call(context, 'modules')
        expect(context.scheduleCreationMode).toBeNull()
    })

    it('starts calculation from the module CTA and renders the automatic, manual, and options cards', async () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const methods = (TimetableV3 as any).methods
        const computed = (TimetableV3 as any).computed
        const timetableCalculationHeading = computed.timetableCalculationHeading
        const currentStepWatcher = (TimetableV3 as any).watch.currentStep
        const push = vi.fn().mockResolvedValue(undefined)
        const context = {
            scheduleCreationMode: 'automatic',
            selectedModuleCount: 0,
            timetableFilters: { include_saturday: false, free_days: null },
            workspaceId: WORKSPACE_ID,
            planningMode: 'with_student',
            selectedStudentCode: '1001',
            isSavingState: false,
            stateSaveFailed: false,
            timetableCalculationStatus: 'idle',
            saveState: vi.fn().mockResolvedValue(undefined),
            calculatePossibleTimetables: vi.fn().mockResolvedValue(undefined),
            ensureValidCurrentStep: vi.fn(),
            resetTimetableCalculation: vi.fn(),
            $router: { push },
        }
        const data = (TimetableV3 as any).data()

        await methods.openTimetableCreationPage.call(context)
        expect(push).not.toHaveBeenCalled()

        context.selectedModuleCount = 6
        await methods.openTimetableCreationPage.call(context)
        expect(context.saveState).toHaveBeenCalledOnce()
        expect(context.timetableFilters.include_saturday).toBe(false)
        expect(push).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v3/creation',
            query: {
                workspace_id: WORKSPACE_ID,
                planning_mode: 'with_student',
                student_code: '1001',
            },
        })
        expect(context.calculatePossibleTimetables).toHaveBeenCalledOnce()
        expect(push.mock.invocationCallOrder[0]).toBeLessThan(
            context.calculatePossibleTimetables.mock.invocationCallOrder[0],
        )

        context.stateSaveFailed = true
        await methods.openTimetableCreationPage.call(context)
        expect(push).toHaveBeenCalledOnce()
        expect(context.calculatePossibleTimetables).toHaveBeenCalledOnce()
        context.stateSaveFailed = false

        context.scheduleCreationMode = 'manual'
        await methods.openTimetableCreationPage.call(context)
        expect(push).toHaveBeenCalledTimes(1)

        methods.returnFromTimetableCreationStep.call(context)
        expect(push).toHaveBeenLastCalledWith({
            path: '/admin/students-timetables/timetable-v3/modules',
            query: {
                workspace_id: WORKSPACE_ID,
                planning_mode: 'with_student',
                student_code: '1001',
            },
        })
        expect(push).toHaveBeenCalledTimes(2)

        context.timetableCalculationStatus = 'calculating'
        methods.returnFromTimetableCreationStep.call(context)
        expect(push).toHaveBeenCalledTimes(2)

        context.timetableCalculationStatus = 'success'
        methods.returnFromTimetableCreationStep.call(context)
        expect(push).toHaveBeenCalledTimes(3)

        context.scheduleCreationMode = 'automatic'
        currentStepWatcher.call(context, 'adoption', 'creation')
        expect(context.scheduleCreationMode).toBe('automatic')
        expect(context.resetTimetableCalculation).not.toHaveBeenCalled()

        currentStepWatcher.call(context, 'modules', 'adoption')
        expect(context.scheduleCreationMode).toBe('automatic')
        expect(context.resetTimetableCalculation).toHaveBeenCalledOnce()

        const creationPageStart = source.indexOf(
            '<template v-else-if="currentStep === \'creation\'">\n            <div class="timetable-v3__creation-summary-cards mt-4">',
        )
        const creationPageEnd = source.indexOf(
            '\n            <template v-else-if="currentStep === \'adoption\'">',
            creationPageStart,
        )
        const creationPageSource = source.slice(creationPageStart, creationPageEnd)
        const automaticCardStart = creationPageSource.indexOf('timetable-v3__creation-summary-card--automatic')
        const automaticCardEnd = creationPageSource.indexOf('</section>', automaticCardStart)
        const automaticCardSource = creationPageSource.slice(automaticCardStart, automaticCardEnd)
        const manualCardStart = creationPageSource.indexOf('timetable-v3__schedule-mode-card--manual')
        const manualCardEnd = creationPageSource.indexOf('</section>', manualCardStart)
        const manualCardSource = creationPageSource.slice(manualCardStart, manualCardEnd)
        const optionsCardStart = creationPageSource.indexOf('timetable-v3__schedule-mode-card--options')
        const optionsCardEnd = creationPageSource.indexOf('</section>', optionsCardStart)
        const optionsCardSource = creationPageSource.slice(optionsCardStart, optionsCardEnd)
        const timetableOutputPosition = creationPageSource.indexOf('<TimetableV3PossibleTimetables')

        expect(creationPageStart).toBeGreaterThan(-1)
        expect(source.indexOf('Aktuelle Auswahl')).toBeLessThan(creationPageStart)
        expect(source).toContain('v-if="isLoadingState" class="timetable-v3__initializing"')
        expect(source).toContain('Gespeicherte Auswahl wird geladen …')
        expect(creationPageSource).toContain('Automatischer Stundenplan')
        expect(creationPageSource).toContain('Ausgewählte Module')
        expect(creationPageSource).not.toContain('Empfohlen')
        expect(creationPageSource).not.toContain('mdi-star-four-points')
        expect(creationPageSource).toContain('v-for="module in selectedModules"')
        expect(creationPageSource).not.toContain('closable')
        expect(creationPageSource).not.toContain('@click:close')
        expect(creationPageSource).not.toContain('timetable-v3__schedule-create-button')
        expect(creationPageSource).not.toContain('timetable-v3__module-workspace-heading')
        expect(creationPageSource).not.toContain('Welche Module sollen zur Stundenplanerstellung berücksichtigt werden?')
        expect(creationPageSource).not.toContain('timetable-v3__module-group-cards')
        expect(creationPageSource).not.toContain('timetable-v3__module-tile')
        expect(automaticCardStart).toBeGreaterThan(-1)
        expect(automaticCardSource).not.toContain('timetable-v3__schedule-mode-label')
        expect(automaticCardSource).toContain('{{ timetableCalculationHeading }}')
        expect(timetableCalculationHeading.call({ timetableCalculationStatus: 'calculating' }))
            .toBe('Berechnung der Stundenpläne')
        expect(timetableCalculationHeading.call({ timetableCalculationStatus: 'success' }))
            .toBe('Ergebnis der Stundenplanberechnung')
        expect(automaticCardSource).toContain("timetableCalculationStatus === 'calculating'")
        expect(automaticCardSource).toContain("timetableCalculationStatus === 'success'")
        expect(automaticCardSource).toContain('possibleTimetableCountLabel')
        expect(automaticCardSource).toContain('checkedTimetableVariationCountLabel')
        expect(automaticCardSource).toContain('conflictingTimetableVariationCountLabel')
        expect(manualCardStart).toBeGreaterThan(-1)
        expect(manualCardSource).toContain('timetable-v3__schedule-mode-card--manual')
        expect(manualCardSource).toContain('timetable-v3__creation-summary-card--manual')
        expect(manualCardSource).toContain('mdi-calendar-edit')
        expect(manualCardSource).not.toContain('timetable-v3__schedule-mode-label')
        expect(manualCardSource).toContain('Manueller Stundenplan')
        expect(manualCardSource).toContain(
            'Sie können jetzt den aktuell ausgewählten Stundenplan übernehmen, um ihn noch weiter',
        )
        expect(manualCardSource).toContain('individuell anzupassen.')
        expect(manualCardSource).toContain('class="timetable-v3__manual-timetable-button"')
        expect(manualCardSource).toContain('prepend-icon="mdi-calendar-import"')
        expect(manualCardSource).toContain('Stundenplan übernehmen')
        expect(manualCardSource).toContain('@click="openTimetableAdoptionPage"')
        expect(manualCardSource).not.toContain('href=')
        expect(manualCardSource).not.toContain(':to=')
        expect(manualCardSource).toContain(":disabled=\"timetableCalculationStatus !== 'success'")
        expect(manualCardSource).toContain('|| !selectedTimetableResult"')
        expect(manualCardSource).not.toContain('timetable-v3__schedule-mode-input')
        expect(optionsCardStart).toBeGreaterThan(manualCardEnd)
        expect(optionsCardSource).toContain('timetable-v3__schedule-mode-card--options')
        expect(optionsCardSource).toContain('mdi-tune-variant')
        expect(optionsCardSource).not.toContain('timetable-v3__schedule-mode-label')
        expect(optionsCardSource).not.toContain(
            '<span class="timetable-v3__schedule-mode-label">Einstellungen</span>',
        )
        expect(optionsCardSource).toContain('Optionen')
        expect(optionsCardSource).not.toContain('Filtern Sie die bereits berechneten Stundenpläne.')
        expect(optionsCardSource).not.toContain('Der gespeicherte Gesamtbestand bleibt unverändert.')
        expect(optionsCardSource).toContain('<v-btn-toggle')
        expect(optionsCardSource).toContain(':model-value="timetableFilters.include_saturday"')
        expect(optionsCardSource).toContain("@update:model-value=\"updateTimetableFilter('include_saturday', $event)\"")
        expect(optionsCardSource).toContain('<span>Ja</span>')
        expect(optionsCardSource).toContain('<span>Nein</span>')
        expect(optionsCardSource).not.toContain('Samstag ja')
        expect(optionsCardSource).not.toContain('Samstag nein')
        expect(optionsCardSource).toContain('Freie Tage')
        expect(optionsCardSource).toContain(':model-value="timetableFilters.free_days"')
        expect(optionsCardSource).toContain("@update:model-value=\"updateTimetableFilter('free_days', $event)\"")
        expect(optionsCardSource).toContain('v-for="option in timetableVisibleFreeDayOptions"')
        expect(optionsCardSource).toContain("timetablePageMeta.optionCounts.includeSaturday > 0")
        expect(optionsCardSource).toContain("timetablePageMeta.optionCounts.excludeSaturday > 0")
        expect(optionsCardSource).toContain('v-if="timetableFreeDayOptionCounts.any > 0"')
        expect(optionsCardSource).toContain('&& timetableHasVisibleFreeDayOptions')
        expect(optionsCardSource).toContain('<span>Egal</span>')
        expect(optionsCardSource).toContain('<span>{{ option.value }}</span>')
        expect(optionsCardSource.match(/class="timetable-v3__filter-option-button-content"/g)).toHaveLength(4)
        expect(optionsCardSource.match(/class="timetable-v3__filter-option-count"/g)).toHaveLength(4)
        expect(optionsCardSource).toContain('{{ timetableFilterOptionCountLabels.includeSaturday }}')
        expect(optionsCardSource).toContain('{{ timetableFilterOptionCountLabels.excludeSaturday }}')
        expect(optionsCardSource).toContain('{{ timetableVariantCountLabel(timetableFreeDayOptionCounts.any) }}')
        expect(optionsCardSource).toContain('{{ timetableVariantCountLabel(option.count) }}')
        expect(source).toMatch(/\.timetable-v3__filter-option-toggle\s*\{[\s\S]*?height:\s*auto;[\s\S]*?min-height:\s*58px;/)
        expect(source).toMatch(/\.timetable-v3__filter-option-count\s*\{[\s\S]*?white-space:\s*nowrap;/)
        expect(source).toMatch(/\.timetable-v3__filter-option-toggle--free-days\s*\{[\s\S]*?grid-template-columns:\s*repeat\(2, minmax\(0, 1fr\)\);/)
        expect(optionsCardSource).not.toContain('&& timetableFilters.include_saturday')
        expect(optionsCardSource).not.toContain('&& !timetableFilters.include_saturday')
        expect(optionsCardSource).toContain('mandatory')
        expect(optionsCardSource).toContain(":aria-busy=\"timetablePageLoading ? 'true' : 'false'\"")
        expect(optionsCardSource).toContain('v-if="timetablePageLoading"')
        expect(optionsCardSource).toContain('class="timetable-v3__filter-loading"')
        expect(optionsCardSource).toContain('role="status"')
        expect(optionsCardSource).toContain('aria-live="polite"')
        expect(optionsCardSource).toContain('Stundenpläne werden mit den gewählten Optionen neu geladen …')
        expect(optionsCardSource).not.toContain('href=')
        expect(optionsCardSource).not.toContain(':to=')
        expect(creationPageSource).not.toContain('timetable-v3__creation-success-card')
        expect(creationPageSource).not.toContain('timetable-v3__calculation-options')
        expect(creationPageSource).not.toContain('Verwendete Module')
        expect(creationPageSource).not.toContain('Samstags Unterricht?')
        expect(creationPageSource).not.toContain('<v-switch')
        expect(creationPageSource).not.toContain('Los!')
        expect(timetableOutputPosition).toBeGreaterThan(optionsCardEnd)
        expect(creationPageSource.slice(timetableOutputPosition)).toContain(
            `v-if="timetableCalculationStatus === 'success' && possibleTimetableCount > 0"`,
        )
        expect(creationPageSource.slice(timetableOutputPosition)).toContain(
            'class="timetable-v3__calculation-output"',
        )
        expect(source).toContain('@click="returnFromTimetableCreationStep"')
        expect(source).toContain('prepend-icon="mdi-arrow-left"')
        expect(source).toContain('Zurück')
        expect(data.timetableFilters).toEqual({ include_saturday: true, free_days: null })
        expect(computed.timetableFilterOptionCountLabels.call({
            timetablePageMeta: {
                optionCounts: {
                    includeSaturday: 250,
                    excludeSaturday: 1,
                    freeDays: {
                        any: 250,
                        maximum: 3,
                        values: [],
                    },
                },
            },
        })).toEqual({
            includeSaturday: '250 Varianten',
            excludeSaturday: '1 Variante',
        })
        expect(computed.timetableVisibleFreeDayOptions.call({
            timetableFreeDayOptionCounts: {
                values: [
                    { value: 3, count: 0 },
                    { value: 2, count: 8 },
                    { value: 1, count: 0 },
                ],
            },
        })).toEqual([{ value: 2, count: 8 }])
        expect(computed.timetableHasVisibleFreeDayOptions.call({
            timetableFreeDayOptionCounts: { any: 0 },
            timetableVisibleFreeDayOptions: [],
        })).toBe(false)
        expect(computed.timetableHasVisibleFreeDayOptions.call({
            timetableFreeDayOptionCounts: { any: 0 },
            timetableVisibleFreeDayOptions: [{ value: 2, count: 8 }],
        })).toBe(true)
        expect(source).toContain('async updateTimetableFilter(filterKey, filterValue)')
        expect(source).toContain('const MAX_FREE_DAYS = 5')
        expect(source).not.toContain('class="timetable-v3__creation-start-button"')
        expect(source).not.toContain('Los!')
        expect(source).toContain('creationOptions: {')
        expect(source).toContain('filters: normalizedTimetableFilters(this.timetableFilters)')
        expect(source).toMatch(/\.timetable-v3__schedule-mode-options--automatic-selected\s*\{[\s\S]*?grid-template-columns:\s*minmax\(0, 2fr\) minmax\(0, 1fr\);/)
        expect(source).toMatch(/\.timetable-v3__creation-summary-cards\s*\{[\s\S]*?grid-template-columns:\s*minmax\(0, 2fr\) minmax\(0, 1fr\);/)
        expect(source).toMatch(/\.timetable-v3__creation-summary-card--automatic\s*\{[\s\S]*?grid-column:\s*1;[\s\S]*?grid-row:\s*1 \/ span 2;/)
        expect(source).toMatch(/\.timetable-v3__creation-summary-card--options\s*\{[\s\S]*?grid-column:\s*2;[\s\S]*?grid-row:\s*2;/)
        expect(source).toMatch(/\.timetable-v3__creation-summary-card\s*\{[\s\S]*?animation: none;[\s\S]*?transition: none;/)
        expect(source).toMatch(/\.timetable-v3__creation-summary-card:hover,[\s\S]*?transform: none;/)
    })

    it('opens a dedicated adoption route only for the selected successful timetable', async () => {
        const methods = (TimetableV3 as any).methods
        const push = vi.fn().mockResolvedValue(undefined)
        const timetable = {
            key: 'full-green-138',
            slots: { '1-1': { code: 'D1' } },
            type: 'full_green',
        }
        const context: any = {
            currentStep: 'creation',
            isLoadingState: false,
            isSavingState: false,
            planningMode: 'with_student',
            saveState: vi.fn().mockResolvedValue(undefined),
            selectedStudentCode: '1001',
            selectedTimetableResult: timetable,
            stateSaveFailed: false,
            timetableAdoptionReturnStep: 'modules',
            timetableCalculationResult: { fingerprint: 'a'.repeat(64) },
            timetableCalculationStatus: 'success',
            timetablePageLoading: false,
            timetableSelectedIndex: 137,
            workspaceId: WORKSPACE_ID,
            $router: { push },
        }

        await methods.openTimetableAdoptionPage.call(context)

        expect(context.timetableAdoptionReturnStep).toBe('creation')
        expect(context.saveState).toHaveBeenCalledOnce()
        expect(push).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v3/adoption',
            query: {
                workspace_id: WORKSPACE_ID,
                planning_mode: 'with_student',
                student_code: '1001',
                fingerprint: 'a'.repeat(64),
                timetable_index: '137',
                timetable_key: 'full-green-138',
            },
        })

        for (const blockedState of [
            { currentStep: 'adoption' },
            { timetableCalculationStatus: 'calculating' },
            { isLoadingState: true },
            { isSavingState: true },
            { timetablePageLoading: true },
            { selectedTimetableResult: null },
            { stateSaveFailed: true },
            { timetableCalculationResult: { fingerprint: 'invalid' } },
        ]) {
            push.mockClear()
            await methods.openTimetableAdoptionPage.call({ ...context, ...blockedState })
            expect(push).not.toHaveBeenCalled()
        }
    })

    it('opens a blank manual timetable from modules without restoring an existing timetable', async () => {
        const methods = (TimetableV3 as any).methods
        const workspaceId = '88791fb1-29c8-4ae9-9d1b-d5c3f5fdbc8c'
        const push = vi.fn().mockResolvedValue(undefined)
        const restorePersistedTimetableCalculation = vi.fn().mockResolvedValue(true)
        const context: any = {
            currentStep: 'modules',
            isLoadingState: false,
            isSavingState: false,
            planningMode: 'with_student',
            restorePersistedTimetableCalculation,
            saveState: vi.fn().mockResolvedValue(undefined),
            scheduleCreationMode: 'automatic',
            selectedStudentCode: '50112620250129',
            selectedTimetableResult: { key: 'backend-full_green-1' },
            stateSaveFailed: false,
            timetableAdoptionReturnStep: 'creation',
            timetableCalculationResult: { fingerprint: 'a'.repeat(64) },
            timetableCalculationStatus: 'success',
            timetablePageLoading: false,
            timetableSelectedIndex: 0,
            workspaceId,
            $router: { push },
        }

        await methods.openManualTimetablePage.call(context)

        expect(restorePersistedTimetableCalculation).not.toHaveBeenCalled()
        expect(context.scheduleCreationMode).toBe('automatic')
        expect(context.timetableAdoptionReturnStep).toBe('modules')
        expect(context.saveState).toHaveBeenCalledOnce()
        expect(push).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v3/adoption',
            query: {
                workspace_id: workspaceId,
                planning_mode: 'with_student',
                student_code: '50112620250129',
            },
        })
    })

    it('always opens the blank manual page in both planning modes', async () => {
        const methods = (TimetableV3 as any).methods
        const push = vi.fn().mockResolvedValue(undefined)
        const context: any = {
            currentStep: 'modules',
            isLoadingState: false,
            isSavingState: false,
            planningMode: 'without_student',
            restorePersistedTimetableCalculation: vi.fn().mockResolvedValue(false),
            saveState: vi.fn().mockResolvedValue(undefined),
            scheduleCreationMode: null,
            selectedStudentCode: '',
            selectedTimetableResult: null,
            stateSaveFailed: false,
            timetableAdoptionReturnStep: 'creation',
            timetablePageLoading: false,
            workspaceId: WORKSPACE_ID,
            $router: { push },
        }

        await methods.openManualTimetablePage.call(context)

        expect(context.restorePersistedTimetableCalculation).not.toHaveBeenCalled()
        expect(context.scheduleCreationMode).toBeNull()
        expect(context.timetableAdoptionReturnStep).toBe('modules')
        expect(context.saveState).toHaveBeenCalledOnce()
        expect(push).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v3/adoption',
            query: {
                workspace_id: WORKSPACE_ID,
                planning_mode: 'without_student',
            },
        })

        push.mockClear()
        context.saveState.mockClear()
        context.planningMode = 'with_student'
        context.selectedStudentCode = '1001'

        await methods.openManualTimetablePage.call(context)

        expect(context.saveState).toHaveBeenCalledOnce()
        expect(push).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v3/adoption',
            query: {
                workspace_id: WORKSPACE_ID,
                planning_mode: 'with_student',
                student_code: '1001',
            },
        })

        push.mockClear()
        context.stateSaveFailed = true

        await methods.openManualTimetablePage.call(context)

        expect(push).not.toHaveBeenCalled()
    })

    it('keeps an intentionally blank manual adoption page valid after reload', async () => {
        const methods = (TimetableV3 as any).methods
        const replace = vi.fn().mockResolvedValue(undefined)
        const context = {
            currentStep: 'adoption',
            hasPlanningSelectionContext: true,
            isManualTimetableAdoption: true,
            planningMode: 'without_student',
            scheduleCreationMode: null,
            selectedModuleCount: 0,
            selectedStudentCode: '',
            studentSelectionDetailsError: false,
            timetableAdoptionReturnStep: 'modules',
            workspaceId: WORKSPACE_ID,
            $router: { replace },
        }

        await methods.ensureValidCurrentStep.call(context)

        expect(replace).not.toHaveBeenCalled()

        const get = vi.fn()
        vi.stubGlobal('axios', { get })

        expect(await methods.restorePersistedTimetableCalculation.call(context)).toBe(false)
        expect(get).not.toHaveBeenCalled()
        expect(replace).not.toHaveBeenCalled()

        await methods.ensureValidCurrentStep.call({
            ...context,
            isManualTimetableAdoption: false,
        })

        expect(replace).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v3/modules',
            query: {
                workspace_id: WORKSPACE_ID,
                planning_mode: 'without_student',
            },
        })
    })

    it('shows the manual adoption card, read-only module catalog, and timetable in order', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const adoptionPageStart = source.indexOf('<template v-else-if="currentStep === \'adoption\'">')
        const adoptionPageEnd = source.indexOf('\n            </template>', adoptionPageStart)
        const adoptionPageSource = source.slice(adoptionPageStart, adoptionPageEnd)
        const manualCardPosition = adoptionPageSource.indexOf('timetable-v3__adoption-card--manual')
        const manualCardEnd = adoptionPageSource.indexOf('</section>', manualCardPosition)
        const manualCardSource = adoptionPageSource.slice(manualCardPosition, manualCardEnd)
        const manualModuleCatalogPosition = adoptionPageSource.indexOf(
            'v-if="isManualTimetableAdoption && !studentSelectionDetailsLoading && !studentSelectionDetailsError"',
        )
        const manualModuleCatalogEnd = adoptionPageSource.indexOf('</section>', manualModuleCatalogPosition)
        const manualModuleCatalogSource = adoptionPageSource.slice(
            manualModuleCatalogPosition,
            manualModuleCatalogEnd,
        )
        const selectedTimetablePosition = adoptionPageSource.indexOf('<TimetableV3PossibleTimetables')
        const currentSelectionPosition = source.lastIndexOf('Aktuelle Auswahl', adoptionPageStart)

        expect(adoptionPageStart).toBeGreaterThan(-1)
        expect(currentSelectionPosition).toBeGreaterThan(source.indexOf('timetable-v3__next-step'))
        expect(currentSelectionPosition).toBeLessThan(adoptionPageStart)
        expect(source).toContain('<div v-if="currentStep !== \'adoption\'">')
        expect(source).toContain("{{ selectedStudentClass || '–' }} · {{ selectedStudentFullName || selectedStudentLabel }}")
        expect(source).toContain('· {{ selectedStudentReligion }}')
        expect(source).toContain('{{ selectedStudentEmail }}')
        expect(source).toContain("v-if=\"currentStep === 'modules' || currentStep === 'adoption'\"")
        expect(source).toContain('aria-labelledby="timetable-v3-compact-planning-title"')
        expect(manualCardPosition).toBeGreaterThan(-1)
        expect(manualCardEnd).toBeGreaterThan(manualCardPosition)
        expect(adoptionPageSource).not.toContain('timetable-v3__adoption-card--automatic')
        expect(adoptionPageSource).not.toContain('Automatischer Stundenplan')
        expect(adoptionPageSource).toContain('Manueller Stundenplan')
        expect(adoptionPageSource).toContain('timetable-v3__schedule-mode-card--selected')
        expect(manualCardSource).not.toContain('timetable-v3__schedule-mode-selected-modules')
        expect(manualCardSource).not.toContain('Ausgewählte Module')
        expect(manualCardSource).not.toContain('{{ selectedModuleCount }}/{{ maximumSelectedModules }} Module')
        expect(manualCardSource).not.toContain('{{ selectedModuleHoursLabel }}/{{ maximumSelectedModuleHours }} Std.')
        expect(manualCardSource).not.toContain('v-for="module in selectedModules"')
        expect(manualModuleCatalogPosition).toBeGreaterThan(manualCardEnd)
        expect(manualModuleCatalogEnd).toBeGreaterThan(manualModuleCatalogPosition)
        expect(manualModuleCatalogSource).toContain('timetable-v3__manual-module-catalog')
        expect(manualModuleCatalogSource).toContain('timetable-v3__manual-module-catalog-headings--with-student')
        expect(manualModuleCatalogSource).toContain(
            "{{ planningMode === 'with_student' ? 'Studierenden Module' : 'Hauptmodule' }}",
        )
        expect(manualModuleCatalogSource).toContain('v-if="planningMode === \'with_student\'"')
        expect(manualModuleCatalogSource).toContain('<h4>Hauptmodule</h4>')
        expect(manualModuleCatalogSource).toContain('aria-label="Hauptmodule"')
        expect(manualModuleCatalogSource).toContain('@click="showManualModuleCatalog(\'student\')"')
        expect(manualModuleCatalogSource).toContain('@click="showManualModuleCatalog(\'main\')"')
        expect(manualModuleCatalogSource).toContain(':aria-pressed="manualModuleCatalogView === \'main\'"')
        expect(manualModuleCatalogSource).toContain('v-for="group in manualModuleCatalogGroups"')
        expect(manualModuleCatalogSource).toContain('manualModuleCatalogUsesMainGroups')
        expect(manualModuleCatalogSource).toContain('{{ selectedModuleCountForGroup(group) }}/{{ group.count }}')
        expect(manualModuleCatalogSource).toContain('timetable-v3__module-group-card--read-only')
        expect(manualModuleCatalogSource).not.toContain('@click="toggleModuleGroup(group)"')
        expect(adoptionPageSource).toContain('<TimetableV3PossibleTimetables')
        expect(adoptionPageSource).toContain('v-if="isManualTimetableAdoption || selectedTimetableResult"')
        expect(adoptionPageSource).toContain(':allow-saturday-lessons="isManualTimetableAdoption || timetableFilters.include_saturday"')
        expect(adoptionPageSource).toContain(':empty-hour-rows="schoolHours"')
        expect(adoptionPageSource).toContain(':empty-timetable="isManualTimetableAdoption"')
        expect(adoptionPageSource).toContain(':navigation-visible="false"')
        expect(adoptionPageSource).toContain(':selected-index="timetableSelectedIndex"')
        expect(adoptionPageSource).toContain(':timetables="timetableCalculationResult?.timetables || []"')
        expect(adoptionPageSource).toContain('class="timetable-v3__page-actions timetable-v3__page-actions--split"')
        expect(adoptionPageSource).toContain('class="timetable-v3__restart-button"')
        expect(adoptionPageSource).toContain('prepend-icon="mdi-restart"')
        expect(adoptionPageSource).toContain(':disabled="!hasPlanningSelectionContext || isLoadingState || isSavingState || timetablePageLoading"')
        expect(adoptionPageSource).toContain('@click="restartPlanning"')
        expect(adoptionPageSource).toContain('Neustart')
        expect(adoptionPageSource).toContain('@click="returnFromTimetableAdoptionStep"')
        expect(adoptionPageSource).toContain('prepend-icon="mdi-arrow-left"')
        expect(adoptionPageSource).toContain('Zurück')
        expect(selectedTimetablePosition).toBeGreaterThan(manualModuleCatalogEnd)
        expect(adoptionPageSource).not.toContain('@navigate')
        expect(adoptionPageSource).not.toContain('Optionen')
        expect(adoptionPageSource.slice(manualCardEnd)).not.toContain('Ausgewählte Module')
        expect(source).toMatch(/\.timetable-v3__adoption-cards\s*\{[\s\S]*?grid-template-columns:\s*minmax\(0, 1fr\);/)
        expect(source).toMatch(/\.timetable-v3__manual-module-catalog-headings--with-student\s*\{[\s\S]*?grid-template-columns:\s*repeat\(2, minmax\(0, 1fr\)\);/)
        expect(source).toMatch(/@media \(max-width: 700px\)[\s\S]*?\.timetable-v3__adoption-cards\s*\{[\s\S]*?grid-template-columns:\s*1fr;/)
        expect(source).toMatch(/@media \(max-width: 700px\)[\s\S]*?\.timetable-v3__manual-module-catalog-headings--with-student\s*\{[\s\S]*?grid-template-columns:\s*1fr;/)
    })

    it('switches the read-only manual catalog between student and all main modules', () => {
        const methods = (TimetableV3 as any).methods
        const computed = (TimetableV3 as any).computed
        const studentGroups = [{ key: 'current', label: 'Aktuelle' }]
        const mainGroups = [{ key: 'BU', code: 'BU', name: 'Biologie' }]
        const context = {
            planningMode: 'with_student',
            manualModuleCatalogView: 'student',
            moduleSelectionGroups: studentGroups,
            mainModuleSelectionGroups: mainGroups,
        }

        expect(computed.manualModuleCatalogGroups.call(context)).toBe(studentGroups)
        expect(computed.manualModuleCatalogUsesMainGroups.call(context)).toBe(false)

        methods.showManualModuleCatalog.call(context, 'main')

        expect(context.manualModuleCatalogView).toBe('main')
        expect(computed.manualModuleCatalogGroups.call(context)).toBe(mainGroups)
        expect(computed.manualModuleCatalogUsesMainGroups.call(context)).toBe(true)

        methods.showManualModuleCatalog.call(context, 'unsupported')
        expect(context.manualModuleCatalogView).toBe('main')

        const withoutStudentContext = {
            ...context,
            planningMode: 'without_student',
            manualModuleCatalogView: 'student',
        }

        methods.showManualModuleCatalog.call(withoutStudentContext, 'main')
        expect(withoutStudentContext.manualModuleCatalogView).toBe('student')
        expect(computed.manualModuleCatalogGroups.call(withoutStudentContext)).toBe(studentGroups)
        expect(computed.manualModuleCatalogUsesMainGroups.call(withoutStudentContext)).toBe(true)
    })

    it('returns from adoption to the remembered source and blocks navigation while loading', () => {
        const methods = (TimetableV3 as any).methods
        const push = vi.fn()
        const context = {
            currentStep: 'adoption',
            isLoadingState: false,
            isSavingState: false,
            planningMode: 'with_student',
            selectedStudentCode: '1001',
            timetableAdoptionReturnStep: 'modules',
            timetablePageLoading: false,
            workspaceId: WORKSPACE_ID,
            $router: { push },
        }

        methods.returnFromTimetableAdoptionStep.call(context)

        expect(push).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v3/modules',
            query: {
                workspace_id: WORKSPACE_ID,
                planning_mode: 'with_student',
                student_code: '1001',
            },
        })

        push.mockClear()
        methods.returnFromTimetableAdoptionStep.call({
            ...context,
            timetableAdoptionReturnStep: 'creation',
        })
        expect(push).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v3/creation',
            query: {
                workspace_id: WORKSPACE_ID,
                planning_mode: 'with_student',
                student_code: '1001',
            },
        })

        for (const blockedState of [
            { currentStep: 'creation' },
            { isLoadingState: true },
            { isSavingState: true },
            { timetablePageLoading: true },
        ]) {
            push.mockClear()
            methods.returnFromTimetableAdoptionStep.call({ ...context, ...blockedState })
            expect(push).not.toHaveBeenCalled()
        }
    })

    it('resolves the exact selected timetable and validates adoption route identity', () => {
        const computed = (TimetableV3 as any).computed
        const result = timetableResultFixture(250, 2)
        const timetablePageMeta = computed.timetablePageMeta.call({
            timetableCalculationResult: result,
            timetableFilters: { include_saturday: true, free_days: null },
        })
        const selectedTimetable = computed.selectedTimetableResult.call({
            timetableCalculationResult: result,
            timetablePageMeta,
            timetableSelectedIndex: 137,
        })

        expect(selectedTimetable).toBe(result.timetables[37])
        expect(selectedTimetable.key).toBe('timetable-138')
        expect(computed.selectedTimetableResult.call({
            timetableCalculationResult: result,
            timetablePageMeta,
            timetableSelectedIndex: 99,
        })).toBeNull()
        expect(computed.timetableAdoptionRouteSelection.call({
            $route: {
                query: {
                    fingerprint: 'a'.repeat(64),
                    timetable_index: '137',
                    timetable_key: 'timetable-138',
                },
            },
        })).toEqual({
            fingerprint: 'a'.repeat(64),
            index: 137,
            key: 'timetable-138',
            page: 2,
        })
        expect(computed.timetableAdoptionRouteSelection.call({
            $route: { query: { fingerprint: 'invalid', timetable_index: '137', timetable_key: 'timetable-138' } },
        })).toBeNull()
        expect(computed.isManualTimetableAdoption.call({
            currentStep: 'adoption',
            timetableAdoptionReturnStep: 'modules',
            timetableAdoptionRouteSelection: null,
        })).toBe(true)
        expect(computed.isManualTimetableAdoption.call({
            currentStep: 'adoption',
            timetableAdoptionReturnStep: 'modules',
            timetableAdoptionRouteSelection: { fingerprint: 'a'.repeat(64) },
        })).toBe(true)
        expect(computed.isManualTimetableAdoption.call({
            currentStep: 'adoption',
            timetableAdoptionReturnStep: 'creation',
            timetableAdoptionRouteSelection: { fingerprint: 'a'.repeat(64) },
        })).toBe(false)
    })

    it('applies timetable options as read-only filters before paging while calculation stays Saturday-inclusive', async () => {
        const methods = (TimetableV3 as any).methods
        const initialResult = timetableResultFixture(250, 1, 250, true, 125, null, [
            { value: 3, count: 83 },
            { value: 2, count: 83 },
            { value: 1, count: 84 },
        ])
        const filteredPageOne = timetableResultFixture(125, 1, 250, false, 125, null, [
            { value: 3, count: 42 },
            { value: 2, count: 41 },
            { value: 1, count: 42 },
        ])
        const filteredPageTwo = timetableResultFixture(125, 2, 250, false, 125, null, [
            { value: 3, count: 42 },
            { value: 2, count: 41 },
            { value: 1, count: 42 },
        ])
        const twoFreeDaysResult = timetableResultFixture(41, 1, 250, false, 41, 2, [
            { value: 3, count: 42 },
            { value: 2, count: 41 },
            { value: 1, count: 42 },
        ], 83, 125)
        const saveState = vi.fn().mockResolvedValue(undefined)
        const get = vi.fn()
            .mockResolvedValueOnce({ data: { data: filteredPageOne } })
            .mockResolvedValueOnce({ data: { data: filteredPageTwo } })
            .mockResolvedValueOnce({ data: { data: twoFreeDaysResult } })
        const fetch = vi.fn()
        vi.stubGlobal('axios', { get })
        vi.stubGlobal('fetch', fetch)
        const context: any = {
            ...timetablePagingContext(initialResult),
            saveState,
        }
        context.reloadTimetableResultsForFilters = () => (
            methods.reloadTimetableResultsForFilters.call(context)
        )

        await methods.updateTimetableFilter.call(context, 'include_saturday', false)

        expect(get).toHaveBeenNthCalledWith(
            1,
            `/api/admin/students-timetables/timetable-v3/timetable?workspace_id=${WORKSPACE_ID}&planning_mode=with_student&student_code=1001&page=1&fingerprint=${'a'.repeat(64)}&filters%5Binclude_saturday%5D=0`,
        )
        expect(context.timetableFilters).toEqual({ include_saturday: false, free_days: null })
        expect(context.timetableCalculationResult.timetables_meta).toMatchObject({
            current_page: 1,
            total: 125,
            unfiltered_total: 250,
            filters: { include_saturday: false, free_days: null },
        })
        expect(context.timetableCalculationResult.summary.timetable_count).toBe(250)
        expect(context.timetableSelectedIndex).toBe(0)
        expect(saveState).toHaveBeenCalledOnce()
        expect(fetch).not.toHaveBeenCalled()

        await methods.selectTimetable.call(context, 100)

        expect(get).toHaveBeenNthCalledWith(
            2,
            `/api/admin/students-timetables/timetable-v3/timetable?workspace_id=${WORKSPACE_ID}&planning_mode=with_student&student_code=1001&page=2&fingerprint=${'a'.repeat(64)}&filters%5Binclude_saturday%5D=0`,
        )
        expect(context.timetableCalculationResult.timetables).toHaveLength(25)
        expect(context.timetableCalculationResult.timetables[0].key).toBe('timetable-101')

        await methods.updateTimetableFilter.call(context, 'free_days', 2)

        expect(get).toHaveBeenNthCalledWith(
            3,
            `/api/admin/students-timetables/timetable-v3/timetable?workspace_id=${WORKSPACE_ID}&planning_mode=with_student&student_code=1001&page=1&fingerprint=${'a'.repeat(64)}&filters%5Binclude_saturday%5D=0&filters%5Bfree_days%5D=2`,
        )
        expect(context.timetableFilters).toEqual({ include_saturday: false, free_days: 2 })
        expect(context.timetableCalculationResult.timetables_meta).toMatchObject({
            current_page: 1,
            total: 41,
            option_counts: {
                include_saturday: 83,
                exclude_saturday: 41,
                free_days: {
                    any: 125,
                    values: [
                        { value: 3, count: 42 },
                        { value: 2, count: 41 },
                        { value: 1, count: 42 },
                    ],
                },
            },
        })
        expect(context.timetableSelectedIndex).toBe(0)

        await methods.updateTimetableFilter.call(context, 'free_days', 2)

        expect(get).toHaveBeenCalledTimes(3)
        expect(saveState).toHaveBeenCalledTimes(2)

        await methods.updateTimetableFilter.call(context, 'free_days', 6)

        expect(get).toHaveBeenCalledTimes(3)
        expect(saveState).toHaveBeenCalledTimes(2)
        expect(context.timetableFilters).toEqual({ include_saturday: false, free_days: 2 })
        expect(methods.timetableCalculationPayload.call({
            workspaceId: WORKSPACE_ID,
            planningMode: 'without_student',
            planningSelectionValues: {},
            selectedCourseKeys: ['d1-a'],
            selectedModuleKeys: ['additional:D1'],
            selectedStudentCode: '',
            timetableFilters: { include_saturday: false, free_days: 2 },
        }).options).toEqual({ allow_saturday_lessons: true })
    })

    it('ignores a stale timetable filter response after a newer view state wins', async () => {
        const methods = (TimetableV3 as any).methods
        const initialResult = timetableResultFixture(250)
        const filteredResult = timetableResultFixture(125, 1, 250, false)
        let resolveFilterRequest: ((value: unknown) => void) | undefined
        const pendingFilterRequest = new Promise(resolve => {
            resolveFilterRequest = resolve
        })
        const get = vi.fn().mockReturnValue(pendingFilterRequest)
        const saveState = vi.fn().mockResolvedValue(undefined)
        vi.stubGlobal('axios', { get })
        const context: any = {
            ...timetablePagingContext(initialResult),
            saveState,
        }
        context.reloadTimetableResultsForFilters = () => (
            methods.reloadTimetableResultsForFilters.call(context)
        )

        const update = methods.updateTimetableFilter.call(context, 'include_saturday', false)
        await vi.waitFor(() => expect(get).toHaveBeenCalledOnce())

        context.timetableFilters = { include_saturday: true, free_days: null }
        context.timetablePageRequestId += 1
        resolveFilterRequest?.({ data: { data: filteredResult } })
        await update

        expect(context.timetableFilters).toEqual({ include_saturday: true, free_days: null })
        expect(context.timetableCalculationResult).toBe(initialResult)
        expect(saveState).not.toHaveBeenCalled()
    })

    it('saves the creation draft before requesting possible timetable variants and ignores a double click', async () => {
        const methods = (TimetableV3 as any).methods
        document.cookie = 'XSRF-TOKEN=secure%3Dtoken; path=/'
        let resolveStateSave: (() => void) | undefined
        const saveState = vi.fn(() => new Promise<void>((resolve) => {
            resolveStateSave = resolve
        }))
        const fetch = vi.fn().mockResolvedValue(timetableCalculationStreamResponse([
            {
                type: 'progress',
                progress_percent: 0,
                combination_count: 0,
                checked_combination_count: 0,
                phase: 'preparing',
            },
            {
                type: 'progress',
                progress_percent: 40,
                combination_count: 10,
                checked_combination_count: 5,
                phase: 'checking',
            },
            {
                type: 'progress',
                progress_percent: 95,
                combination_count: 10,
                checked_combination_count: 10,
                phase: 'persisting',
            },
            {
                type: 'progress',
                progress_percent: 100,
                combination_count: 10,
                checked_combination_count: 10,
                phase: 'complete',
            },
            {
                type: 'complete',
                data: {
                    summary: {
                        timetable_count: 7,
                        timetable_variation_count: 10,
                        conflict_timetable_count: 3,
                    },
                    ...timetablePageFixture(7),
                },
            },
        ]))
        vi.stubGlobal('fetch', fetch)
        const context: any = {
            currentStep: 'creation',
            workspaceId: WORKSPACE_ID,
            selectedModuleKeys: ['current:D5', 'additional:M5'],
            selectedCourseKeys: ['d5-a', 'm5-b'],
            planningMode: 'with_student',
            selectedStudentCode: '1001',
            planningSelectionValues: {
                religion: 'ETH',
                language: 'F',
                branch: null,
                arts_subject: null,
            },
            timetableFilters: { include_saturday: true, free_days: null },
            stateSaveFailed: false,
            timetableCalculationStatus: 'idle',
            timetableCalculationResult: null,
            timetableCalculationError: '',
            timetableCalculationRequestId: 0,
            timetableCalculationCombinationCount: 0,
            timetableCalculationCheckedCombinationCount: 0,
            timetableCalculationProgressPercent: 0,
            timetableCalculationProgressPhase: 'preparing',
            saveState,
        }
        context.timetableCalculationPayload = () => methods.timetableCalculationPayload.call(context)
        context.timetableCalculationErrorMessage = (error: unknown) => (
            methods.timetableCalculationErrorMessage.call(context, error)
        )

        const calculation = methods.calculatePossibleTimetables.call(context)
        const duplicateCalculation = methods.calculatePossibleTimetables.call(context)

        expect(context.timetableCalculationStatus).toBe('calculating')
        expect(saveState).toHaveBeenCalledOnce()
        expect(fetch).not.toHaveBeenCalled()

        await duplicateCalculation
        resolveStateSave?.()
        await calculation

        expect(fetch).toHaveBeenCalledOnce()
        expect(fetch).toHaveBeenCalledWith('/api/admin/students-timetables/timetable-v3/timetable', {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json, application/x-ndjson',
                'Content-Type': 'application/json',
                'X-Timetable-Progress': 'stream',
                'X-XSRF-TOKEN': 'secure=token',
            },
            body: JSON.stringify({
                modules: ['current:D5', 'additional:M5'],
                parameters: {
                    workspace_id: WORKSPACE_ID,
                    planning_mode: 'with_student',
                    student_code: '1001',
                    selection: {
                        religion: 'ETH',
                        language: 'F',
                        branch: null,
                        arts_subject: null,
                    },
                    selected_course_keys: ['d5-a', 'm5-b'],
                },
                options: {
                    allow_saturday_lessons: true,
                },
            }),
        })
        expect(context.timetableCalculationStatus).toBe('success')
        expect(context.timetableCalculationResult.summary.timetable_count).toBe(7)
        expect(context.timetableCalculationCombinationCount).toBe(10)
        expect(context.timetableCalculationCheckedCombinationCount).toBe(10)
        expect(context.timetableCalculationProgressPercent).toBe(100)
        expect(context.timetableCalculationProgressPhase).toBe('complete')
    })

    it('keeps a successful calculation when its active view filter cannot be loaded', async () => {
        const methods = (TimetableV3 as any).methods
        const unfilteredResult = timetableResultFixture(1)
        const fetch = vi.fn().mockResolvedValue(timetableCalculationStreamResponse([{
            type: 'complete',
            data: unfilteredResult,
        }]))
        const get = vi.fn().mockRejectedValue(new Error('offline'))
        vi.stubGlobal('fetch', fetch)
        vi.stubGlobal('axios', { get })
        const context: any = {
            ...timetableCalculationContext(methods),
            timetableFilters: { include_saturday: false, free_days: null },
            timetablePageError: '',
            timetablePageLoading: false,
            timetablePageLoadingDirection: '',
            timetablePageRequestId: 0,
            timetableSelectedIndex: 0,
            timetableCalculationResultMatchesCurrentDraft: vi.fn().mockReturnValue(true),
        }
        context.reloadTimetableResultsForFilters = () => (
            methods.reloadTimetableResultsForFilters.call(context)
        )

        await methods.calculatePossibleTimetables.call(context)

        expect(fetch).toHaveBeenCalledOnce()
        expect(get).toHaveBeenCalledOnce()
        expect(context.timetableCalculationStatus).toBe('success')
        expect(context.timetableCalculationResult).toEqual(unfilteredResult)
        expect(context.timetableFilters).toEqual({ include_saturday: true, free_days: null })
        expect(context.timetablePageError).toBe(
            'Die ausgewählten Optionen konnten nicht angewendet werden. Das vollständige Ergebnis wird angezeigt.',
        )
        expect(context.saveState).toHaveBeenCalledTimes(2)
    })

    it('preserves the authorization error when filtering a newly calculated result is forbidden', async () => {
        const methods = (TimetableV3 as any).methods
        const fetch = vi.fn().mockResolvedValue(timetableCalculationStreamResponse([{
            type: 'complete',
            data: timetableResultFixture(1),
        }]))
        const get = vi.fn().mockRejectedValue({ response: { status: 403 } })
        vi.stubGlobal('fetch', fetch)
        vi.stubGlobal('axios', { get })
        const context: any = {
            ...timetableCalculationContext(methods),
            timetableFilters: { include_saturday: false, free_days: null },
            timetablePageError: '',
            timetablePageLoading: false,
            timetablePageLoadingDirection: '',
            timetablePageRequestId: 0,
            timetableSelectedIndex: 0,
            timetableCalculationResultMatchesCurrentDraft: vi.fn().mockReturnValue(true),
        }
        context.reloadTimetableResultsForFilters = () => (
            methods.reloadTimetableResultsForFilters.call(context)
        )

        await methods.calculatePossibleTimetables.call(context)

        expect(context.timetableCalculationStatus).toBe('error')
        expect(context.timetableCalculationResult).toBeNull()
        expect(context.timetableCalculationError).toContain('Berechtigung')
        expect(context.saveState).toHaveBeenCalledOnce()
    })

    it('refreshes an expired XSRF cookie once before retrying the streamed calculation', async () => {
        const methods = (TimetableV3 as any).methods
        const context = timetableCalculationContext(methods)
        document.cookie = 'XSRF-TOKEN=expired%3Dtoken; path=/'
        const fetch = vi.fn()
            .mockResolvedValueOnce({
                ok: false,
                status: 419,
                text: vi.fn().mockResolvedValue(''),
            })
            .mockResolvedValueOnce(timetableCalculationStreamResponse([{
                type: 'complete',
                data: {
                    summary: { timetable_count: 1 },
                    ...timetablePageFixture(1),
                },
            }]))
        const get = vi.fn().mockImplementation(async () => {
            document.cookie = 'XSRF-TOKEN=fresh%3Dtoken; path=/'
        })
        vi.stubGlobal('fetch', fetch)
        vi.stubGlobal('axios', { get })

        await methods.calculatePossibleTimetables.call(context)

        expect(get).toHaveBeenCalledWith('/sanctum/csrf-cookie', { __skipCsrfRetry: true })
        expect(fetch).toHaveBeenCalledTimes(2)
        expect(fetch.mock.calls[0][1].headers['X-XSRF-TOKEN']).toBe('expired=token')
        expect(fetch.mock.calls[1][1].headers['X-XSRF-TOKEN']).toBe('fresh=token')
        expect(context.timetableCalculationStatus).toBe('success')
    })

    it('applies streamed calculation phases before completion across split UTF-8 chunks', async () => {
        const methods = (TimetableV3 as any).methods
        const encoder = new TextEncoder()
        let streamController: ReadableStreamDefaultController<Uint8Array> | undefined
        const stream = new ReadableStream<Uint8Array>({
            start(controller) {
                streamController = controller
            },
        })
        const fetch = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            body: stream,
            text: vi.fn(),
        })
        vi.stubGlobal('fetch', fetch)
        const context: any = {
            currentStep: 'creation',
            workspaceId: WORKSPACE_ID,
            selectedModuleKeys: ['current:D5'],
            selectedCourseKeys: ['d5-a'],
            planningMode: 'without_student',
            selectedStudentCode: '',
            planningSelectionValues: {},
            timetableFilters: { include_saturday: true, free_days: null },
            stateSaveFailed: false,
            timetableCalculationStatus: 'idle',
            timetableCalculationResult: null,
            timetableCalculationError: '',
            timetableCalculationRequestId: 0,
            timetableCalculationCombinationCount: 0,
            timetableCalculationCheckedCombinationCount: 0,
            timetableCalculationProgressPercent: 0,
            timetableCalculationProgressPhase: 'preparing',
            saveState: vi.fn().mockResolvedValue(undefined),
        }
        context.timetableCalculationPayload = () => methods.timetableCalculationPayload.call(context)
        context.timetableCalculationErrorMessage = (error: unknown) => (
            methods.timetableCalculationErrorMessage.call(context, error)
        )

        const calculation = methods.calculatePossibleTimetables.call(context)
        await vi.waitFor(() => expect(fetch).toHaveBeenCalledOnce())

        streamController?.enqueue(encoder.encode([
            JSON.stringify({
                type: 'progress',
                progress_percent: 0,
                combination_count: 0,
                checked_combination_count: 0,
                phase: 'preparing',
            }),
            JSON.stringify({
                type: 'progress',
                progress_percent: 0,
                combination_count: 18432,
                checked_combination_count: 0,
                phase: 'checking',
            }),
            '',
        ].join('\n')))
        await vi.waitFor(() => expect(context.timetableCalculationProgressPhase).toBe('checking'))

        const checkingBytes = encoder.encode(`${JSON.stringify({
            type: 'progress',
            progress_percent: 40,
            combination_count: 18432,
            checked_combination_count: 9216,
            phase: 'checking',
            detail: 'Prüfung',
        })}\r\n`)
        const umlautByteIndex = checkingBytes.findIndex(byte => byte === 0xc3)
        streamController?.enqueue(checkingBytes.slice(0, umlautByteIndex + 1))
        await Promise.resolve()
        expect(context.timetableCalculationCheckedCombinationCount).toBe(0)

        streamController?.enqueue(checkingBytes.slice(umlautByteIndex + 1))
        await vi.waitFor(() => expect(context.timetableCalculationProgressPhase).toBe('checking'))
        expect(context.timetableCalculationStatus).toBe('calculating')
        expect(context.timetableCalculationCombinationCount).toBe(18432)
        expect(context.timetableCalculationCheckedCombinationCount).toBe(9216)
        expect(context.timetableCalculationProgressPercent).toBe(40)

        streamController?.enqueue(encoder.encode(`${JSON.stringify({
            type: 'progress',
            progress_percent: 95,
            combination_count: 18432,
            checked_combination_count: 18432,
            phase: 'persisting',
        })}\n`))
        await vi.waitFor(() => expect(context.timetableCalculationProgressPhase).toBe('persisting'))
        expect(context.timetableCalculationStatus).toBe('calculating')

        streamController?.enqueue(encoder.encode(JSON.stringify({
            type: 'complete',
            data: {
                summary: {
                    timetable_count: 500,
                    timetable_variation_count: 18432,
                },
                ...timetablePageFixture(500),
            },
        })))
        streamController?.close()
        await calculation

        expect(context.timetableCalculationStatus).toBe('success')
        expect(context.timetableCalculationProgressPhase).toBe('complete')
        expect(context.timetableCalculationProgressPercent).toBe(100)
    })

    it('keeps navigation inside the loaded page without another backend request', async () => {
        const methods = (TimetableV3 as any).methods
        const context: any = timetablePagingContext()
        const get = vi.fn()
        vi.stubGlobal('axios', { get })

        await methods.selectTimetable.call(context, 37)

        expect(get).not.toHaveBeenCalled()
        expect(context.timetableSelectedIndex).toBe(37)
        expect(context.timetableCalculationResult.timetables[0].key).toBe('timetable-1')
    })

    it('replaces the active 100 timetable page in both boundary directions', async () => {
        const methods = (TimetableV3 as any).methods
        const pageOne = timetableResultFixture(500, 1)
        const pageTwo = timetableResultFixture(500, 2)
        const context: any = timetablePagingContext(pageOne)
        context.timetableSelectedIndex = 99
        const get = vi.fn()
            .mockResolvedValueOnce({ data: { data: pageTwo } })
            .mockResolvedValueOnce({ data: { data: pageOne } })
        vi.stubGlobal('axios', { get })

        await methods.selectTimetable.call(context, 100)

        expect(get).toHaveBeenNthCalledWith(
            1,
            `/api/admin/students-timetables/timetable-v3/timetable?workspace_id=${WORKSPACE_ID}&planning_mode=with_student&student_code=1001&page=2&fingerprint=${'a'.repeat(64)}&filters%5Binclude_saturday%5D=1`,
        )
        expect(context.timetableCalculationResult.timetables).toHaveLength(100)
        expect(context.timetableCalculationResult.timetables[0].key).toBe('timetable-101')
        expect(context.timetableCalculationResult.timetables.some(({ key }: { key: string }) => key === 'timetable-1')).toBe(false)
        expect(context.timetableSelectedIndex).toBe(100)

        await methods.selectTimetable.call(context, 99)

        expect(get).toHaveBeenNthCalledWith(
            2,
            `/api/admin/students-timetables/timetable-v3/timetable?workspace_id=${WORKSPACE_ID}&planning_mode=with_student&student_code=1001&page=1&fingerprint=${'a'.repeat(64)}&filters%5Binclude_saturday%5D=1`,
        )
        expect(context.timetableCalculationResult.timetables).toHaveLength(100)
        expect(context.timetableCalculationResult.timetables[0].key).toBe('timetable-1')
        expect(context.timetableCalculationResult.timetables.some(({ key }: { key: string }) => key === 'timetable-101')).toBe(false)
        expect(context.timetableSelectedIndex).toBe(99)
    })

    it('loads the twentieth page when 2000 timetables were materialized', async () => {
        const methods = (TimetableV3 as any).methods
        const pageNineteen = timetableResultFixture(2000, 19)
        const pageTwenty = timetableResultFixture(2000, 20)
        const context: any = timetablePagingContext(pageNineteen)
        context.timetableSelectedIndex = 1899
        const get = vi.fn().mockResolvedValue({ data: { data: pageTwenty } })
        vi.stubGlobal('axios', { get })

        await methods.selectTimetable.call(context, 1900)

        expect(get).toHaveBeenCalledWith(
            `/api/admin/students-timetables/timetable-v3/timetable?workspace_id=${WORKSPACE_ID}&planning_mode=with_student&student_code=1001&page=20&fingerprint=${'a'.repeat(64)}&filters%5Binclude_saturday%5D=1`,
        )
        expect(context.timetableCalculationResult.timetables).toHaveLength(100)
        expect(context.timetableCalculationResult.timetables[0].key).toBe('timetable-1901')
        expect(context.timetableCalculationResult.timetables[99].key).toBe('timetable-2000')
        expect(context.timetableSelectedIndex).toBe(1900)
    })

    it('keeps the current page after a transient failure and retries the same boundary', async () => {
        const methods = (TimetableV3 as any).methods
        const pageOne = timetableResultFixture(500, 1)
        const context: any = timetablePagingContext(pageOne)
        context.timetableSelectedIndex = 99
        const get = vi.fn()
            .mockRejectedValueOnce(new Error('offline'))
            .mockResolvedValueOnce({ data: { data: timetableResultFixture(500, 2) } })
        vi.stubGlobal('axios', { get })

        await methods.selectTimetable.call(context, 100)

        expect(context.timetableCalculationResult).toBe(pageOne)
        expect(context.timetableSelectedIndex).toBe(99)
        expect(context.timetablePageError).toContain('nächsten Stundenpläne')
        expect(context.timetablePageLoading).toBe(false)

        await methods.selectTimetable.call(context, 100)

        expect(get).toHaveBeenCalledTimes(2)
        expect(context.timetableCalculationResult.timetables[0].key).toBe('timetable-101')
        expect(context.timetableSelectedIndex).toBe(100)
        expect(context.timetablePageError).toBe('')
    })

    it('ignores a stale page response after reset and clears results after an authorization failure', async () => {
        const methods = (TimetableV3 as any).methods
        let resolvePage: ((value: unknown) => void) | undefined
        const pendingResponse = new Promise(resolve => {
            resolvePage = resolve
        })
        const context: any = timetablePagingContext()
        context.timetableSelectedIndex = 99
        const get = vi.fn()
            .mockReturnValueOnce(pendingResponse)
            .mockRejectedValueOnce({ response: { status: 403 } })
        vi.stubGlobal('axios', { get })

        const staleRequest = methods.selectTimetable.call(context, 100)
        await vi.waitFor(() => expect(get).toHaveBeenCalledOnce())
        methods.resetTimetableCalculation.call(context)
        resolvePage?.({ data: { data: timetableResultFixture(500, 2) } })
        await staleRequest

        expect(context.timetableCalculationStatus).toBe('idle')
        expect(context.timetableCalculationResult).toBeNull()

        Object.assign(context, timetablePagingContext())
        context.timetableSelectedIndex = 99
        await methods.selectTimetable.call(context, 100)

        expect(context.timetableCalculationResult).toBeNull()
        expect(context.timetableCalculationStatus).toBe('error')
        expect(context.timetableCalculationError).toContain('Berechtigung')
    })

    it('shows only the possible variant count and explains the calculation compactly', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const computed = (TimetableV3 as any).computed
        const methods = (TimetableV3 as any).methods
        const countContext = {
            timetablePageMeta: { total: 0 },
            timetableCalculationSummary: {
                timetable_count: 0,
                timetable_variation_count: 12,
                conflict_timetable_count: 12,
            },
            normalizedTimetableCalculationCount: methods.normalizedTimetableCalculationCount,
        }

        expect(computed.possibleTimetableCount.call(countContext)).toBe(0)
        expect(computed.filteredOutAllTimetables.call({
            timetablePageMeta: { total: 0, unfilteredTotal: 156 },
        })).toBe(true)
        expect(computed.filteredOutAllTimetables.call({
            timetablePageMeta: { total: 0, unfilteredTotal: 0 },
        })).toBe(false)
        expect(source).toContain('Berechnung der Stundenpläne')
        expect(source).toContain('Kombinationen werden vorbereitet …')
        expect(source).toContain('von ${this.timetableCalculationCombinationCountLabel} Kombinationen geprüft.')
        expect(source).toContain('Mögliche Stundenpläne werden aufbereitet …')
        expect(source).toContain('Lösungsvorschläge werden berechnet …')
        expect(source).toContain('Stundenplandaten werden komprimiert …')
        expect(source).toContain('Ergebnis wird gespeichert …')
        expect(source).toContain('% Gesamtfortschritt')
        expect(source).toContain('aria-busy="true"')
        expect(source).toContain('role="progressbar"')
        expect(source).toContain('Fortschritt der Stundenplanberechnung')
        expect(source).toContain("Array.from({ length: 20 }")
        expect(source).toContain('(index + 1) * 5')
        expect(source).toContain('timetable-v3__calculation-led-segment--active')
        expect(source).toMatch(/class="timetable-v3__calculation-result"\s+aria-live="polite"\s+role="status"/)
        expect(source).toContain("timetableCalculationSummary.timetable_count")
        expect(source).toContain('Keine möglichen Varianten gefunden')
        expect(source).toContain('Keine Varianten entsprechen den gewählten Optionen')
        expect(source).not.toContain('Die ausgewählten Unterrichtsalternativen wurden miteinander kombiniert')
        expect(source).not.toContain('timetable-v3__calculation-description')
        expect(source).toContain('timetableCalculationSummary.timetable_variation_count')
        expect(source).toContain('timetableCalculationSummary.conflict_timetable_count')
        expect(source).toContain('TimetableV3PossibleTimetables')
        expect(source).toContain(':timetables="timetableCalculationResult?.timetables || []"')

        const progressSegments = computed.timetableCalculationProgressSegments.call({
            timetableCalculationProgressPercent: 25,
        })
        const summaryContext: any = {
            timetableCalculationSummary: {
                timetable_count: 2000,
                possible_timetable_count: 2500,
                timetables_truncated: true,
            },
            possibleTimetableCount: 2000,
            normalizedTimetableCalculationCount: methods.normalizedTimetableCalculationCount,
        }
        summaryContext.totalPossibleTimetableCount = computed.totalPossibleTimetableCount.call(summaryContext)

        expect(progressSegments).toHaveLength(20)
        expect(progressSegments.filter((segment: any) => segment.active)).toHaveLength(5)
        expect(progressSegments[4]).toEqual({ percent: 25, active: true })
        expect(progressSegments[5]).toEqual({ percent: 30, active: false })
        expect(computed.timetableCalculationProgressLabel.call({
            timetableCalculationProgressPhase: 'checking',
            timetableCalculationCombinationCount: 18432,
            timetableCalculationCheckedCombinationCountLabel: '9.216',
            timetableCalculationCombinationCountLabel: '18.432',
        })).toBe('9.216 von 18.432 Kombinationen geprüft.')
        expect(computed.timetableCalculationProgressLabel.call({
            timetableCalculationProgressPhase: 'persisting',
            timetableCalculationCombinationCount: 18432,
        })).toBe('Ergebnis wird gespeichert …')
        expect(summaryContext.totalPossibleTimetableCount).toBe(2500)
        expect(computed.timetablesTruncated.call(summaryContext)).toBe(true)
        expect(source).toContain('Insgesamt wurden <strong>{{ unfilteredMaterializedTimetableCountLabel }}</strong> von')
        expect(source).toContain('{{ totalPossibleTimetableCountLabel }}</strong> möglichen Stundenplänen')
        expect(source).toContain('gespeichert. Die Optionen verändern diesen Bestand nicht.')
    })

    it('keeps backend calculation module details out of the static manual creation card', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const manualCardStart = source.indexOf('timetable-v3__creation-summary-card--manual')
        const manualCardEnd = source.indexOf('</section>', manualCardStart)
        const manualCardSource = source.slice(manualCardStart, manualCardEnd)

        expect(manualCardSource).toContain('Manueller Stundenplan')
        expect(manualCardSource).not.toContain('Verwendete Module')
        expect(manualCardSource).not.toContain('timetableCalculationModules')
        expect(source).not.toContain('timetableCalculationModules()')
    })

    it('renders backend-ranked module removal solutions as explicit actions only when they can help', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const computed = (TimetableV3 as any).computed
        const methods = (TimetableV3 as any).methods
        const scenarios = [
            {
                removed_module_selection_key: 'current:CH1',
                removed_module_code: 'CH1',
                removed_module_name: 'Chemie 1',
                possible_timetable_count: 18,
                full_green_timetable_count: 18,
                green_timetable_count: 0,
                status: 'calculated',
            },
            {
                removed_module_selection_key: 'current:BU2',
                removed_module_code: 'BU2',
                removed_module_name: 'Biologie 2',
                possible_timetable_count: null,
                full_green_timetable_count: null,
                green_timetable_count: null,
                status: 'combination_limit_exceeded',
            },
        ]
        const solutionPlan = computed.timetableSolutionPlan.call({
            timetableCalculationSummary: {
                solution_plan: {
                    strategy: 'remove_one_module',
                    module_removal_scenarios: scenarios,
                },
            },
        })
        const renderedScenarios = computed.timetableSolutionPlanModuleRemovalScenarios.call({
            timetableSolutionPlan: solutionPlan,
        })
        const normalizedTimetableCalculationCount = methods.normalizedTimetableCalculationCount
        const hasOnlyZeroScenarios = computed.timetableSolutionPlanHasOnlyZeroCalculatedScenarios.call({
            timetableSolutionPlanModuleRemovalScenarios: [
                { status: 'calculated', possible_timetable_count: 0 },
                { status: 'calculated', possible_timetable_count: 0 },
            ],
            normalizedTimetableCalculationCount,
        })
        const hasUnknownScenario = computed.timetableSolutionPlanHasOnlyZeroCalculatedScenarios.call({
            timetableSolutionPlanModuleRemovalScenarios: [
                { status: 'calculated', possible_timetable_count: 0 },
                { status: 'combination_limit_exceeded', possible_timetable_count: null },
            ],
            normalizedTimetableCalculationCount,
        })
        const solutionPlanSource = source.slice(
            source.indexOf('class="timetable-v3__calculation-output timetable-v3__solution-plan"'),
            source.indexOf(
                '</section>',
                source.indexOf('class="timetable-v3__calculation-output timetable-v3__solution-plan"'),
            ),
        )

        expect(renderedScenarios).toEqual(scenarios)
        expect(renderedScenarios.map((scenario: any) => scenario.removed_module_code)).toEqual(['CH1', 'BU2'])
        expect(hasOnlyZeroScenarios).toBe(true)
        expect(hasUnknownScenario).toBe(false)
        expect(methods.timetableSolutionPlanCountLabel.call({
            normalizedTimetableCalculationCount,
        }, 1200)).toBe((1200).toLocaleString('de-AT'))
        expect(source).toContain("v-if=\"timetableCalculationStatus === 'success'")
        expect(source).toContain('&& timetablePageMeta.unfilteredTotal === 0')
        expect(source).toContain('&& timetableSolutionPlanModuleRemovalScenarios.length')
        expect(solutionPlanSource).toContain('timetable-v3__calculation-output timetable-v3__solution-plan')
        expect(solutionPlanSource).toContain('Lösungsplan')
        expect(solutionPlanSource).toContain('scenario.removed_module_code')
        expect(solutionPlanSource).toContain('scenario.removed_module_name')
        expect(solutionPlanSource).toContain('scenario.possible_timetable_count')
        expect(solutionPlanSource).toContain("scenario.status === 'calculated'")
        expect(solutionPlanSource).toContain("scenario.status === 'combination_limit_exceeded'")
        expect(solutionPlanSource).not.toContain('Beste Möglichkeit')
        expect(solutionPlanSource).toContain('Auch ohne dieses Modul keine mögliche Variante')
        expect(solutionPlanSource).toContain(
            'Auch durch das Entfernen eines einzelnen Moduls entsteht noch kein möglicher Stundenplan.',
        )
        expect(solutionPlanSource).toContain('Die Prüfung allein verändert Ihre Auswahl nicht.')
        expect(solutionPlanSource).toContain('@click="applyTimetableSolutionPlanScenario(scenario)"')
        expect(solutionPlanSource).toContain(':disabled="!timetableSolutionPlanScenarioApplicable(scenario)')
        expect(solutionPlanSource).toContain('Anklicken und neu berechnen')
        expect(solutionPlanSource).not.toContain('.sort(')
    })

    it('applies a positive solution by exact module key, saves it, and recalculates once', async () => {
        const methods = (TimetableV3 as any).methods
        const events: string[] = []
        const fetch = vi.fn(async () => {
            events.push('fetch')

            return timetableCalculationStreamResponse([{
                type: 'complete',
                data: {
                    summary: { timetable_count: 7, timetable_variation_count: 7 },
                    ...timetablePageFixture(7),
                },
            }])
        })
        vi.stubGlobal('fetch', fetch)
        const scenario = {
            removed_module_selection_key: 'current:CH1',
            removed_module_code: 'CH1',
            possible_timetable_count: 18,
            status: 'calculated',
        }
        const context: any = {
            currentStep: 'creation',
            isSavingState: false,
            stateSaveFailed: false,
            timetableCalculationStatus: 'success',
            timetableCalculationResult: { summary: { timetable_count: 0 } },
            timetableCalculationError: '',
            timetableCalculationRequestId: 0,
            selectedModuleKeys: ['current:CH1', 'current:D5'],
            selectedCourseKeys: ['ch1-a', 'ch1-b', 'd5-a'],
            planningMode: 'with_student',
            selectedStudentCode: '1001',
            planningSelectionValues: { language: 'F' },
            timetableFilters: { include_saturday: true, free_days: null },
            moduleSelectionGroups: [{
                modules: [
                    { selection_key: 'current:CH1', courses: [{ keys: ['ch1-a', 'ch1-b'] }] },
                    { selection_key: 'current:D5', courses: [{ keys: ['d5-a'] }] },
                ],
            }],
            moduleSelectionLimitMessage: 'old',
            normalizedTimetableCalculationCount: methods.normalizedTimetableCalculationCount,
            timetableSolutionPlanScenarioApplicable: (candidate: unknown) => (
                methods.timetableSolutionPlanScenarioApplicable.call(context, candidate)
            ),
            timetableCalculationResultMatchesCurrentDraft: vi.fn().mockReturnValue(true),
            resetTimetableCalculation: vi.fn(() => {
                methods.resetTimetableCalculation.call(context)
                events.push('reset')
            }),
            timetableCalculationPayload: () => methods.timetableCalculationPayload.call(context),
            timetableCalculationErrorMessage: (error: unknown) => (
                methods.timetableCalculationErrorMessage.call(context, error)
            ),
            calculatePossibleTimetables: vi.fn(async () => {
                events.push('calculate')
                await methods.calculatePossibleTimetables.call(context)
            }),
            saveState: vi.fn(async () => {
                events.push('save')
            }),
        }

        await methods.applyTimetableSolutionPlanScenario.call(context, scenario)

        expect(context.selectedModuleKeys).toEqual(['current:D5'])
        expect(context.selectedCourseKeys).toEqual(['d5-a'])
        expect(context.moduleSelectionLimitMessage).toBe('')
        expect(events).toEqual(['reset', 'calculate', 'save', 'fetch'])
        expect(context.saveState).toHaveBeenCalledOnce()
        expect(context.calculatePossibleTimetables).toHaveBeenCalledOnce()
        expect(fetch).toHaveBeenCalledOnce()
        expect(fetch.mock.invocationCallOrder[0]).toBeGreaterThan(context.saveState.mock.invocationCallOrder[0])
        expect(context.timetableCalculationStatus).toBe('success')
        expect(context.timetableCalculationResult.summary.timetable_count).toBe(7)

        context.timetableCalculationStatus = 'success'
        await methods.applyTimetableSolutionPlanScenario.call(context, {
            ...scenario,
            removed_module_selection_key: 'current:D5',
            possible_timetable_count: 0,
        })
        await methods.applyTimetableSolutionPlanScenario.call(context, {
            ...scenario,
            removed_module_selection_key: 'current:D5',
            possible_timetable_count: null,
            status: 'combination_limit_exceeded',
        })

        expect(context.calculatePossibleTimetables).toHaveBeenCalledOnce()
    })

    it('shows the fallback error when a successful calculation response has no object summary', async () => {
        const methods = (TimetableV3 as any).methods
        const fetch = vi.fn().mockResolvedValue(timetableCalculationStreamResponse([{
            type: 'complete',
            data: {
                data: {
                    summary: [],
                    timetables: [],
                },
            },
        }]))
        vi.stubGlobal('fetch', fetch)
        const context: any = {
            currentStep: 'creation',
            selectedModuleKeys: ['current:D5'],
            selectedCourseKeys: ['d5-a'],
            planningMode: 'without_student',
            selectedStudentCode: '',
            planningSelectionValues: {},
            timetableFilters: { include_saturday: true, free_days: null },
            stateSaveFailed: false,
            timetableCalculationStatus: 'idle',
            timetableCalculationResult: null,
            timetableCalculationError: '',
            timetableCalculationRequestId: 0,
            saveState: vi.fn().mockResolvedValue(undefined),
        }
        context.timetableCalculationPayload = () => methods.timetableCalculationPayload.call(context)
        context.timetableCalculationErrorMessage = (error: unknown) => (
            methods.timetableCalculationErrorMessage.call(context, error)
        )

        await methods.calculatePossibleTimetables.call(context)

        expect(context.timetableCalculationStatus).toBe('error')
        expect(context.timetableCalculationResult).toBeNull()
        expect(context.timetableCalculationError).toBe('Bitte versuchen Sie die Berechnung erneut.')
    })

    it('preserves the selected options and the server error when calculation fails', async () => {
        const methods = (TimetableV3 as any).methods
        const fetch = vi.fn().mockResolvedValue(timetableCalculationStreamResponse([{
            type: 'error',
            message: 'Für diese Auswahl fehlen Unterrichtsdaten.',
        }]))
        vi.stubGlobal('fetch', fetch)
        const context: any = {
            currentStep: 'creation',
            selectedModuleKeys: ['current:D5'],
            selectedCourseKeys: ['d5-a'],
            planningMode: 'without_student',
            selectedStudentCode: '',
            planningSelectionValues: {},
            timetableFilters: { include_saturday: false, free_days: null },
            stateSaveFailed: false,
            timetableCalculationStatus: 'idle',
            timetableCalculationResult: null,
            timetableCalculationError: '',
            timetableCalculationRequestId: 0,
            saveState: vi.fn().mockResolvedValue(undefined),
        }
        context.timetableCalculationPayload = () => methods.timetableCalculationPayload.call(context)
        context.timetableCalculationErrorMessage = (error: unknown) => (
            methods.timetableCalculationErrorMessage.call(context, error)
        )

        await methods.calculatePossibleTimetables.call(context)

        expect(context.timetableCalculationStatus).toBe('error')
        expect(context.timetableCalculationError).toBe('Für diese Auswahl fehlen Unterrichtsdaten.')
        expect(context.selectedModuleKeys).toEqual(['current:D5'])
        expect(context.selectedCourseKeys).toEqual(['d5-a'])
        expect(context.timetableFilters.include_saturday).toBe(false)
    })

    it('shows the specific validation error when a selected module cannot be calculated', async () => {
        const methods = (TimetableV3 as any).methods

        expect(methods.timetableCalculationErrorMessage.call({}, {
            response: {
                data: {
                    message: 'The given data was invalid.',
                    errors: {
                        modules: [
                            'Nicht alle ausgewählten Module konnten für die Berechnung aufgelöst werden: Rev2.',
                        ],
                    },
                },
            },
        })).toBe('Nicht alle ausgewählten Module konnten für die Berechnung aufgelöst werden: Rev2.')
    })

    it('ignores a calculation response after its in-memory request was reset', async () => {
        const methods = (TimetableV3 as any).methods
        let resolveCalculation: ((value: string) => void) | undefined
        const fetch = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            body: null,
            text: vi.fn(() => new Promise<string>((resolve) => {
                resolveCalculation = resolve
            })),
        })
        vi.stubGlobal('fetch', fetch)
        const context: any = {
            currentStep: 'creation',
            selectedModuleKeys: ['current:D5'],
            selectedCourseKeys: ['d5-a'],
            planningMode: 'without_student',
            selectedStudentCode: '',
            planningSelectionValues: {},
            timetableFilters: { include_saturday: true, free_days: null },
            stateSaveFailed: false,
            timetableCalculationStatus: 'idle',
            timetableCalculationResult: null,
            timetableCalculationError: '',
            timetableCalculationRequestId: 0,
            saveState: vi.fn().mockResolvedValue(undefined),
        }
        context.timetableCalculationPayload = () => methods.timetableCalculationPayload.call(context)
        context.timetableCalculationErrorMessage = (error: unknown) => (
            methods.timetableCalculationErrorMessage.call(context, error)
        )

        const calculation = methods.calculatePossibleTimetables.call(context)
        await vi.waitFor(() => expect(fetch).toHaveBeenCalledOnce())

        methods.resetTimetableCalculation.call(context)
        resolveCalculation?.(JSON.stringify({
            type: 'complete',
            data: {
                data: {
                    summary: { timetable_count: 99 },
                },
            },
        }))
        await calculation

        expect(context.timetableCalculationStatus).toBe('idle')
        expect(context.timetableCalculationResult).toBeNull()
    })

    it('waits for persisted module data before rendering a reloaded creation page', async () => {
        const methods = (TimetableV3 as any).methods
        const callOrder: string[] = []
        const context = {
            storedState: {
                entrySelection: {
                    mode: 'with_student',
                    student: { studentCode: '1001' },
                },
                creationOptions: { filters: { include_saturday: false, free_days: 2 } },
            },
            planningMode: null,
            selectedStudent: null,
            timetableFilters: { include_saturday: true, free_days: null },
            resetSelectedStudentSelectionDetails: vi.fn(),
            loadSelectedStudentSelection: vi.fn(async () => {
                callOrder.push('modules')
            }),
            hydrateSelectedStudentDetails: vi.fn(async () => {
                callOrder.push('student')
            }),
            restoreCreationScheduleMode: vi.fn(() => {
                callOrder.push('mode')
            }),
        }

        methods.restoreCreationOptions.call(context)
        await methods.restoreEntrySelection.call(context)

        expect(context.timetableFilters).toEqual({ include_saturday: false, free_days: 2 })
        expect(context.planningMode).toBe('with_student')
        expect(context.selectedStudent).toEqual({ studentCode: '1001' })
        expect(callOrder).toEqual(['modules', 'student', 'mode'])
    })

    it('restores valid timetable filters and otherwise uses the complete result', () => {
        const methods = (TimetableV3 as any).methods
        const cases = [
            [
                { creationOptions: { filters: { include_saturday: false, free_days: 3 } } },
                { include_saturday: false, free_days: 3 },
            ],
            [
                { creationOptions: { filters: { include_saturday: false, free_days: 5 } } },
                { include_saturday: false, free_days: 5 },
            ],
            [
                { creationOptions: { filters: { include_saturday: true, free_days: null } } },
                { include_saturday: true, free_days: null },
            ],
            [
                { creationOptions: { filters: { include_saturday: 'no', free_days: 6 } } },
                { include_saturday: true, free_days: null },
            ],
            [
                { creationOptions: { allowSaturdayLessons: false } },
                { include_saturday: true, free_days: null },
            ],
            [{}, { include_saturday: true, free_days: null }],
        ] as const

        for (const [storedState, expectedFilters] of cases) {
            const context: any = {
                storedState,
                timetableFilters: { include_saturday: false, free_days: 1 },
            }

            methods.restoreCreationOptions.call(context)

            expect(context.timetableFilters).toEqual(expectedFilters)
        }
    })

    it('restores only whitelisted adoption return steps', () => {
        const methods = (TimetableV3 as any).methods

        for (const [storedReturnStep, expectedReturnStep] of [
            ['modules', 'modules'],
            ['creation', 'creation'],
            ['https://example.com', 'creation'],
            [null, 'creation'],
        ]) {
            const context: any = {
                storedState: { adoptionReturnStep: storedReturnStep },
                timetableAdoptionReturnStep: 'modules',
            }

            methods.restoreAdoptionReturnStep.call(context)

            expect(context.timetableAdoptionReturnStep).toBe(expectedReturnStep)
        }
    })

    it('restores only a persisted creation result matching the fully hydrated draft', async () => {
        const methods = (TimetableV3 as any).methods
        const solutionPlan = {
            strategy: 'remove_one_module',
            module_removal_scenarios: [{
                removed_module_selection_key: 'current:CH1',
                removed_module_code: 'CH1',
                possible_timetable_count: 18,
                status: 'calculated',
            }],
        }
        const persistedResult = {
            context: {
                workspace_id: WORKSPACE_ID,
                planning_mode: 'with_student',
                student_code: '1001',
            },
            modules: [{
                selection_key: 'current:CH1',
                selected_course_keys: ['ch1-a', 'ch1-b'],
            }],
            parameters: {
                workspace_id: WORKSPACE_ID,
                planning_mode: 'with_student',
                student_code: '1001',
                selection: {
                    semester: 5,
                    religion: 'Rev',
                    language: 'F',
                    branch: null,
                    arts_subject: null,
                },
                selected_course_keys: ['ch1-b', 'ch1-a'],
                constraints: { availableWeekdays: [1, 2, 3, 4, 5, 6] },
            },
            summary: { timetable_count: 0, solution_plan: solutionPlan },
            ...timetablePageFixture(0),
        }
        const get = vi.fn().mockResolvedValue({ data: { data: persistedResult } })
        vi.stubGlobal('axios', { get })
        const context: any = {
            currentStep: 'creation',
            workspaceId: WORKSPACE_ID,
            hasPlanningSelectionContext: true,
            planningMode: 'with_student',
            selectedStudentCode: '1001',
            selectedStudent: { studentCode: '1001', semester: 5 },
            studentSelectionDetailsError: false,
            scheduleCreationMode: 'automatic',
            selectedModuleKeys: ['current:CH1'],
            selectedCourseKeys: ['ch1-a', 'ch1-b'],
            planningSelectionValues: {
                religion: 'Rev',
                language: 'F',
                branch: null,
                arts_subject: null,
            },
            timetableFilters: { include_saturday: true, free_days: null },
            timetableCalculationStatus: 'idle',
            timetableCalculationResult: null,
            timetableCalculationError: '',
            normalizedStudentSemester: methods.normalizedStudentSemester,
            timetableCalculationResultMatchesCurrentDraft: (candidate: unknown) => (
                methods.timetableCalculationResultMatchesCurrentDraft.call(context, candidate)
            ),
        }

        await methods.restorePersistedTimetableCalculation.call(context)

        expect(get).toHaveBeenCalledWith(
            `/api/admin/students-timetables/timetable-v3/timetable?workspace_id=${WORKSPACE_ID}&planning_mode=with_student&student_code=1001&page=1&filters%5Binclude_saturday%5D=1`,
        )
        expect(context.timetableCalculationStatus).toBe('success')
        expect(context.timetableCalculationResult.summary.solution_plan).toEqual(solutionPlan)

        context.timetableCalculationStatus = 'idle'
        context.timetableCalculationResult = null
        context.selectedModuleKeys = ['current:D5']
        await methods.restorePersistedTimetableCalculation.call(context)
        expect(context.timetableCalculationStatus).toBe('idle')
        expect(context.timetableCalculationResult).toBeNull()

        context.selectedModuleKeys = ['current:CH1']
        context.selectedCourseKeys = ['ch1-a']
        await methods.restorePersistedTimetableCalculation.call(context)
        expect(context.timetableCalculationStatus).toBe('idle')
        expect(context.timetableCalculationResult).toBeNull()

        context.selectedCourseKeys = ['ch1-a', 'ch1-b']
        context.planningSelectionValues.language = 'L'
        await methods.restorePersistedTimetableCalculation.call(context)
        expect(context.timetableCalculationStatus).toBe('idle')
        expect(context.timetableCalculationResult).toBeNull()

        context.planningSelectionValues.language = 'F'
        context.timetableFilters = { include_saturday: false }
        persistedResult.timetables_meta.filters.include_saturday = false
        await methods.restorePersistedTimetableCalculation.call(context)
        expect(context.timetableCalculationStatus).toBe('success')
        expect(context.timetableCalculationResult).toBe(persistedResult)

        context.timetableCalculationStatus = 'idle'
        context.timetableCalculationResult = null
        persistedResult.parameters.selection.semester = 4
        await methods.restorePersistedTimetableCalculation.call(context)
        expect(context.timetableCalculationStatus).toBe('idle')
        expect(context.timetableCalculationResult).toBeNull()

        context.currentStep = 'modules'
        await methods.restorePersistedTimetableCalculation.call(context)
        expect(get).toHaveBeenCalledTimes(6)

        const restoredFromModules = await methods.restorePersistedTimetableCalculation.call(context)

        expect(get).toHaveBeenCalledTimes(6)
        expect(restoredFromModules).toBe(false)
        expect(context.timetableCalculationStatus).toBe('idle')
        expect(context.timetableCalculationResult).toBeNull()
    })

    it('restores the exact adopted timetable page and rejects stale route identity', async () => {
        const methods = (TimetableV3 as any).methods
        const persistedResult = timetableResultFixture(250, 2)
        const get = vi.fn().mockResolvedValue({ data: { data: persistedResult } })
        const replace = vi.fn().mockResolvedValue(undefined)
        vi.stubGlobal('axios', { get })
        const context: any = {
            currentStep: 'adoption',
            hasPlanningSelectionContext: true,
            planningMode: 'without_student',
            scheduleCreationMode: 'automatic',
            selectedCourseKeys: ['d1-a'],
            selectedModuleKeys: ['additional:D1'],
            selectedStudentCode: '',
            studentSelectionDetailsError: false,
            timetableAdoptionRouteSelection: {
                fingerprint: 'a'.repeat(64),
                index: 137,
                key: 'timetable-138',
                page: 2,
            },
            timetableCalculationError: '',
            timetableCalculationResult: null,
            timetableCalculationResultMatchesCurrentDraft: vi.fn().mockReturnValue(true),
            timetableCalculationStatus: 'idle',
            timetableFilters: { include_saturday: true, free_days: null },
            timetablePageRequestId: 0,
            timetableSelectedIndex: 0,
            workspaceId: WORKSPACE_ID,
            replaceInvalidTimetableAdoption: () => methods.replaceInvalidTimetableAdoption.call(context),
            $router: { replace },
        }

        await methods.restorePersistedTimetableCalculation.call(context)

        expect(get).toHaveBeenCalledWith(expect.stringContaining(
            `page=2&fingerprint=${'a'.repeat(64)}`,
        ))
        expect(context.timetableCalculationStatus).toBe('success')
        expect(context.timetableSelectedIndex).toBe(137)
        expect(context.timetableCalculationResult.timetables[37].key).toBe('timetable-138')
        expect(replace).not.toHaveBeenCalled()

        context.timetableAdoptionRouteSelection = {
            ...context.timetableAdoptionRouteSelection,
            fingerprint: 'b'.repeat(64),
        }
        await methods.restorePersistedTimetableCalculation.call(context)

        expect(context.timetableCalculationStatus).toBe('idle')
        expect(context.timetableCalculationResult).toBeNull()
        expect(replace).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v3/creation',
            query: {
                workspace_id: WORKSPACE_ID,
                planning_mode: 'without_student',
            },
        })
    })

    it('keeps a hydrated creation draft idle when no saved result exists or loading it fails', async () => {
        const methods = (TimetableV3 as any).methods
        const context: any = {
            currentStep: 'creation',
            hasPlanningSelectionContext: true,
            planningMode: 'without_student',
            selectedStudentCode: '',
            studentSelectionDetailsError: false,
            scheduleCreationMode: 'automatic',
            selectedModuleKeys: ['additional:D1'],
            selectedCourseKeys: ['d1-a'],
            planningSelectionValues: {},
            timetableFilters: { include_saturday: false, free_days: null },
            timetableCalculationStatus: 'idle',
            timetableCalculationResult: null,
            timetableCalculationError: '',
            timetableCalculationResultMatchesCurrentDraft: (candidate: unknown) => (
                methods.timetableCalculationResultMatchesCurrentDraft.call(context, candidate)
            ),
        }
        const get = vi.fn()
            .mockResolvedValueOnce({ data: { data: null } })
            .mockRejectedValueOnce(new Error('offline'))
        vi.stubGlobal('axios', { get })

        await methods.restorePersistedTimetableCalculation.call(context)
        await methods.restorePersistedTimetableCalculation.call(context)

        expect(get).toHaveBeenNthCalledWith(
            1,
            '/api/admin/students-timetables/timetable-v3/timetable?planning_mode=without_student&page=1&filters%5Binclude_saturday%5D=0',
        )
        expect(context.timetableCalculationStatus).toBe('idle')
        expect(context.timetableCalculationResult).toBeNull()
    })

    it('ignores stale restore responses and rejects a non-first persisted page', async () => {
        const methods = (TimetableV3 as any).methods
        let resolveRestore: ((value: unknown) => void) | undefined
        const pendingRestore = new Promise(resolve => {
            resolveRestore = resolve
        })
        const get = vi.fn()
            .mockReturnValueOnce(pendingRestore)
            .mockResolvedValueOnce({ data: { data: timetableResultFixture(101, 2) } })
        vi.stubGlobal('axios', { get })
        const context: any = {
            currentStep: 'creation',
            hasPlanningSelectionContext: true,
            planningMode: 'without_student',
            scheduleCreationMode: 'automatic',
            selectedCourseKeys: ['d1-a'],
            selectedModuleKeys: ['additional:D1'],
            selectedStudentCode: '',
            studentSelectionDetailsError: false,
            timetableCalculationError: '',
            timetableCalculationResult: null,
            timetableCalculationResultMatchesCurrentDraft: vi.fn().mockReturnValue(true),
            timetableCalculationStatus: 'idle',
            timetablePageRequestId: 0,
            workspaceId: WORKSPACE_ID,
        }

        const staleRestore = methods.restorePersistedTimetableCalculation.call(context)
        await vi.waitFor(() => expect(get).toHaveBeenCalledOnce())
        methods.resetTimetableCalculation.call(context)
        resolveRestore?.({ data: { data: timetableResultFixture(1) } })
        await staleRestore

        expect(context.timetableCalculationStatus).toBe('idle')
        expect(context.timetableCalculationResult).toBeNull()

        await methods.restorePersistedTimetableCalculation.call(context)

        expect(get).toHaveBeenCalledTimes(2)
        expect(context.timetableCalculationStatus).toBe('idle')
        expect(context.timetableCalculationResult).toBeNull()
    })

    it('keeps the initial loading gate active until draft validation and result restoration finish', async () => {
        const methods = (TimetableV3 as any).methods
        const loadingStates: boolean[] = []
        const context: any = {
            isLoadingState: false,
            stateLoadFailed: false,
            storedState: null,
            workspaceId: WORKSPACE_ID,
            $route: { query: {} },
            restoreCreationOptions: vi.fn(),
            restoreAdoptionReturnStep: vi.fn(),
            restoreEntrySelection: vi.fn(async () => {
                loadingStates.push(context.isLoadingState)
            }),
            ensureValidCurrentStep: vi.fn(async () => {
                loadingStates.push(context.isLoadingState)
            }),
            restorePersistedTimetableCalculation: vi.fn(async () => {
                loadingStates.push(context.isLoadingState)
            }),
        }
        vi.stubGlobal('axios', {
            get: vi.fn().mockResolvedValue({ data: { data: { state: { draft: true } } } }),
        })

        await methods.loadState.call(context)

        expect(loadingStates).toEqual([true, true, true])
        expect(context.isLoadingState).toBe(false)
        expect(context.storedState).toEqual({ draft: true })
    })

    it('assigns a stable workspace to a tab before loading its draft', async () => {
        const methods = (TimetableV3 as any).methods
        const replace = vi.fn().mockResolvedValue(undefined)
        vi.stubGlobal('crypto', { randomUUID: () => WORKSPACE_ID })
        const context: any = {
            workspaceId: '',
            $route: {
                path: '/admin/students-timetables/timetable-v3/creation',
                query: {
                    planning_mode: 'with_student',
                    student_code: 'same-student',
                },
            },
            $router: { replace },
        }

        await methods.initializeWorkspace.call(context)

        expect(context.workspaceId).toBe(WORKSPACE_ID)
        expect(replace).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v3/creation',
            query: {
                workspace_id: WORKSPACE_ID,
                planning_mode: 'with_student',
                student_code: 'same-student',
            },
        })

        replace.mockClear()
        await methods.initializeWorkspace.call({
            workspaceId: '',
            $route: {
                path: '/admin/students-timetables/timetable-v3/creation',
                query: { workspace_id: WORKSPACE_ID },
            },
            $router: { replace },
        })
        expect(replace).not.toHaveBeenCalled()
    })

    it('reloads the draft belonging to the workspace in the current browser tab', async () => {
        const methods = (TimetableV3 as any).methods
        const context: any = {
            isLoadingState: false,
            stateLoadFailed: false,
            storedState: null,
            workspaceId: WORKSPACE_ID,
            $route: {
                query: {
                    planning_mode: 'with_student',
                    student_code: 'student-03',
                },
            },
            restoreCreationOptions: vi.fn(),
            restoreAdoptionReturnStep: vi.fn(),
            restoreEntrySelection: vi.fn().mockResolvedValue(undefined),
            ensureValidCurrentStep: vi.fn().mockResolvedValue(undefined),
            restorePersistedTimetableCalculation: vi.fn().mockResolvedValue(undefined),
        }
        const get = vi.fn().mockResolvedValue({
            data: {
                data: {
                    state: { tab: 'student-03' },
                },
            },
        })
        vi.stubGlobal('axios', { get })

        await methods.loadState.call(context)

        expect(get).toHaveBeenCalledWith(
            `/api/admin/students-timetables/timetable-v3-state?workspace_id=${WORKSPACE_ID}`,
        )
        expect(context.storedState).toEqual({ tab: 'student-03' })
    })

    it('shows main modules before concrete modules without a student', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const methods = (TimetableV3 as any).methods
        const usesMainModuleGroups = (TimetableV3 as any).computed.usesMainModuleGroups
        const mainModuleGroup = {
            key: 'BU',
            code: 'BU',
            name: 'Biologie',
            label: 'BU Biologie',
            count: 2,
            modules: [
                { selection_key: 'additional:BU1', code: 'BU1', name: 'Biologie 1' },
                { selection_key: 'additional:BU2', code: 'BU2', name: 'Biologie 2' },
            ],
        }
        const context = {
            activeModuleGroupKey: '',
            selectedModuleKeys: ['additional:D1'],
            selectedCourseKeys: ['d1-a'],
        }

        expect(usesMainModuleGroups.call({ planningMode: 'without_student' })).toBe(true)
        expect(usesMainModuleGroups.call({ planningMode: 'with_student' })).toBe(false)
        methods.toggleModuleGroup.call(context, mainModuleGroup)
        expect(context.activeModuleGroupKey).toBe('BU')
        expect(context.selectedModuleKeys).toEqual(['additional:D1'])
        expect(context.selectedCourseKeys).toEqual(['d1-a'])
        expect(methods.moduleGroupIcon(mainModuleGroup)).toBe('mdi-bookshelf')
        methods.closeModuleGroup.call(context)
        expect(context.activeModuleGroupKey).toBe('')

        expect(source).toContain('Hauptmodule')
        expect(source).toContain('Wählen Sie zuerst ein Hauptmodul.')
        expect(source).toContain("usesMainModuleGroups ? 'Hauptmodule' : 'Modularten'")
        expect(source).toContain('{{ group.code }}')
        expect(source).toContain('{{ group.name }}')
        expect(source).toContain("'timetable-v3__module-group-card--main'")
        expect(source).toContain("'timetable-v3__module-group-panel--main'")
        expect(source).toContain("usesMainModuleGroups ? 'Hauptmodul schließen' : 'Modulart schließen'")
        expect(source).toContain('@click="openModuleCoursesDialog(module)"')
        expect(source).toContain('grid-template-columns: repeat(auto-fill, minmax(175px, 1fr))')
        expect(source).not.toContain('unter „Zusätzliche“ angeboten')
    })

    it('orders selected module summaries alphabetically by module code on modules and creation', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const selectedModules = (TimetableV3 as any).computed.selectedModules
        const selectedModuleKeys = [
            'current:M3',
            'current:D2',
            'current:ETH3',
            'current:CH1',
            'current:D10',
        ]
        const originalSelectedModuleKeys = [...selectedModuleKeys]
        const moduleSelectionGroups = [{
            modules: [
                { selection_key: 'current:D10', code: 'D10' },
                { selection_key: 'current:M3', code: 'M3' },
                { selection_key: 'current:CH1', code: 'CH1' },
                { selection_key: 'current:D2', code: 'D2' },
                { selection_key: 'current:ETH3', code: 'ETH3' },
            ],
        }]
        const originalModules = [...moduleSelectionGroups[0].modules]

        expect(selectedModules.call({ moduleSelectionGroups, selectedModuleKeys })
            .map((module: { code: string }) => module.code))
            .toEqual(['CH1', 'D2', 'D10', 'ETH3', 'M3'])
        expect(selectedModuleKeys).toEqual(originalSelectedModuleKeys)
        expect(moduleSelectionGroups[0].modules).toEqual(originalModules)
        expect(source.match(/v-for="module in selectedModules"/g)).toHaveLength(2)
        expect(source.match(/Ausgewählte Module/g)).toHaveLength(2)
    })

    it('opens a persistent course dialog from an individual module tile', async () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const methods = (TimetableV3 as any).methods
        const selectedModules = (TimetableV3 as any).computed.selectedModules
        const selectedModuleHours = (TimetableV3 as any).computed.selectedModuleHours
        const selectedModuleHoursLabel = (TimetableV3 as any).computed.selectedModuleHoursLabel
        const activeModuleSelectionGroup = (TimetableV3 as any).computed.activeModuleSelectionGroup
        const saveState = vi.fn().mockResolvedValue(undefined)
        const groups = [
            {
                key: 'finished',
                count: 1,
                modules: [{
                    selection_key: 'finished:D1',
                    code: 'D1',
                    name: 'Deutsch 1',
                    hours: 1.5,
                    courses: [{ key: 'd1-a', title: 'D1 - 1A - MA' }],
                }],
            },
            {
                key: 'current',
                count: 1,
                modules: [{
                    selection_key: 'current:M5',
                    code: 'M5',
                    name: 'Mathematik 5',
                    hours: 2,
                    courses: [
                        {
                            key: 'm5-a-1',
                            keys: ['m5-a-1', 'm5-a-2'],
                            title: 'M5 - 3A - HU',
                            schedule_labels: [
                                'Montag · 09:50–10:40 · 1-wöchig',
                                'Montag · 10:40–11:30 · 2-wöchig',
                            ],
                            scheduled_hours: 1,
                            usual_hours: 2,
                            hours_label: '1 von 2 Std.',
                            instruction_label: 'Fernunterricht',
                        },
                        { key: 'm5-b', keys: ['m5-b'], title: 'M5 - 3B - KO' },
                    ],
                }],
            },
        ]
        const context = {
            selectedModuleKeys: [],
            selectedCourseKeys: [],
            moduleSelectionGroups: groups,
            moduleSelectionLimitMessage: '',
            moduleCoursesDialogOpen: false,
            moduleCourseDialogModule: null,
            moduleCourseDialogCourses: groups[1].modules[0].courses,
            moduleCourseSelected: methods.moduleCourseSelected,
            selectedCourseCountForModule: methods.selectedCourseCountForModule,
            saveState,
        }
        const groupContext = {
            activeModuleGroupKey: '',
            selectedModuleKeys: ['current:M5'],
            selectedCourseKeys: ['m5-a-1', 'm5-a-2'],
        }

        methods.openModuleCoursesDialog.call(context, groups[1].modules[0])

        expect(context.moduleCoursesDialogOpen).toBe(true)
        expect(context.moduleCourseDialogModule).toBe(groups[1].modules[0])
        await methods.toggleModuleCourse.call(context, groups[1].modules[0].courses[0])
        expect(context.selectedCourseKeys).toEqual(['m5-a-1', 'm5-a-2'])
        expect(context.selectedModuleKeys).toEqual(['current:M5'])
        expect(methods.selectedCourseCountForModule.call(context, groups[1].modules[0])).toBe(1)
        expect(saveState).toHaveBeenCalledOnce()
        await methods.toggleModuleCourse.call(context, groups[1].modules[0].courses[0])
        expect(context.selectedCourseKeys).toEqual([])
        expect(context.selectedModuleKeys).toEqual([])
        expect(saveState).toHaveBeenCalledTimes(2)
        await methods.selectAllModuleCourses.call(context)
        expect(context.selectedCourseKeys).toEqual(['m5-a-1', 'm5-a-2', 'm5-b'])
        expect(context.selectedModuleKeys).toEqual(['current:M5'])
        expect(methods.selectedCourseCountForModule.call(context, groups[1].modules[0])).toBe(2)
        expect(saveState).toHaveBeenCalledTimes(3)
        await methods.deselectAllModuleCourses.call(context)
        expect(context.selectedCourseKeys).toEqual([])
        expect(context.selectedModuleKeys).toEqual([])
        expect(saveState).toHaveBeenCalledTimes(4)
        expect(methods.courseSelectionKeys(groups[1].modules[0].courses[0])).toEqual(['m5-a-1', 'm5-a-2'])
        expect(methods.courseScheduleLabels(groups[1].modules[0].courses[0])).toEqual([
            'Montag · 09:50–10:40 · 1-wöchig',
            'Montag · 10:40–11:30 · 2-wöchig',
        ])
        expect(methods.courseScheduleLabels({
            display_schedule_labels: ['Montag · 20:25–21:55 · 17.02.2026'],
            schedule_labels: [
                'Montag · 20:25–21:10 · 1-wöchig',
                'Montag · 21:10–21:55 · 1-wöchig',
            ],
        })).toEqual(['Montag · 20:25–21:55 · 17.02.2026'])
        methods.closeModuleCoursesDialog.call(context)
        expect(context.moduleCoursesDialogOpen).toBe(false)
        methods.toggleModuleGroup.call(groupContext, groups[0])
        expect(groupContext.activeModuleGroupKey).toBe('finished')
        expect(methods.moduleGroupActive.call(groupContext, groups[0])).toBe(true)
        methods.closeModuleGroup.call(groupContext)
        expect(groupContext.activeModuleGroupKey).toBe('')
        expect(groupContext.selectedModuleKeys).toEqual(['current:M5'])
        expect(groupContext.selectedCourseKeys).toEqual(['m5-a-1', 'm5-a-2'])
        methods.toggleModuleGroup.call(groupContext, groups[0])
        methods.toggleModuleGroup.call(groupContext, groups[0])
        expect(groupContext.activeModuleGroupKey).toBe('')
        expect(methods.moduleGroupActive.call(groupContext, groups[0])).toBe(false)
        expect(methods.moduleGroupIcon(groups[0])).toBe('mdi-check-decagram-outline')
        expect(methods.moduleGroupIcon({ key: 'unknown' })).toBe('mdi-view-grid-outline')
        expect(selectedModules.call({
            moduleSelectionGroups: groups,
            selectedModuleKeys: ['current:M5'],
        })).toEqual([groups[1].modules[0]])
        expect(selectedModuleHours.call({
            selectedModules: [groups[0].modules[0], groups[1].modules[0]],
        })).toBe(3.5)
        expect(selectedModuleHoursLabel.call({ selectedModuleHours: 3.5 })).toBe('3,5')
        expect(activeModuleSelectionGroup.call({
            activeModuleGroupKey: 'finished',
            moduleSelectionGroups: groups,
        })).toEqual(groups[0])
        expect(activeModuleSelectionGroup.call({
            activeModuleGroupKey: '',
            moduleSelectionGroups: groups,
        })).toBeNull()
        expect(source).toContain('v-for="group in moduleSelectionGroups"')
        expect(source).toContain('timetable-v3__schedule-mode-selected-modules')
        expect(source).not.toContain('class="timetable-v3__selected-modules mt-3"')
        expect(source).toContain('v-for="module in selectedModules"')
        expect(source).toContain('Ausgewählte Module')
        expect(source).toContain('{{ selectedModuleCount }}/{{ maximumSelectedModules }} Module')
        expect(source).toContain('· {{ selectedModuleHoursLabel }}/{{ maximumSelectedModuleHours }} Std.')
        expect(source).toContain('Keine Module ausgewählt.')
        expect(source).not.toContain('Module suchen')
        expect(source).not.toContain('moduleSearch')
        expect(source).toContain('class="timetable-v3__module-group-card"')
        expect(source).toContain('class="timetable-v3__module-group-panel"')
        expect(source).toContain('@click="toggleModuleGroup(group)"')
        expect(source).toContain('class="timetable-v3__module-group-panel-close"')
        expect(source).toMatch(/class="timetable-v3__module-group-panel-close"[\s\S]*?color="orange-darken-2"/)
        expect(source).toContain('<v-icon icon="mdi-close" size="20" />')
        expect(source).toContain('height="34"')
        expect(source).toContain('min-width="34"')
        expect(source).toContain('rounded="sm"')
        expect(source).toContain('variant="flat"')
        expect(source).toContain('width="34"')
        expect(source).toContain(":aria-label=\"usesMainModuleGroups ? 'Hauptmodul schließen' : 'Modulart schließen'\"")
        expect(source).toContain('@click="closeModuleGroup"')
        expect(source).toContain('v-if="activeModuleSelectionGroup"')
        expect(source).toMatch(/<transition[\s\S]*?name="timetable-v3-module-panel"[\s\S]*?mode="out-in">/)
        expect(source).toContain(':icon="moduleGroupIcon(group)"')
        expect(source).toContain('class="timetable-v3__module-tile"')
        expect(source).toContain(':aria-pressed="moduleSelected(module)"')
        expect(source).toContain('@click="openModuleCoursesDialog(module)"')
        expect(source).toContain('v-model="moduleCoursesDialogOpen" max-width="820" persistent scrollable')
        expect(source).toContain('v-for="course in moduleCourseDialogCourses"')
        expect(source).toContain('role="checkbox"')
        expect(source).toContain('@click="toggleModuleCourse(course)"')
        expect(source).toContain('@click="selectAllModuleCourses"')
        expect(source).toContain('@click="deselectAllModuleCourses"')
        expect(source).toMatch(/@click="closeModuleCoursesDialog">\s*Bestätigen/)
        expect(source).not.toMatch(/@click="closeModuleCoursesDialog">\s*Schließen/)
        expect(source).toContain('{{ selectedCourseCountForModule(module) }}/{{ moduleCourseCount(module) }} Unterrichte')
        expect(source).toContain("Unterrichte für {{ moduleCourseDialogModule?.code || 'Modul' }}")
        expect(source).toContain('von {{ moduleCourseDialogCourses.length }} Unterrichten ausgewählt')
        expect(source).toContain('Für dieses Modul sind keine Unterrichte im importierten Stundenplan vorhanden.')
        expect(source).not.toContain('Kurse für {{ moduleCourseDialogModule')
        expect(source).not.toContain('Kursen ausgewählt')
        expect(source).not.toContain('keine Kurse im importierten Stundenplan')
        expect(source).toContain('Alle auswählen')
        expect(source).toContain('Alle abwählen')
        expect(source).toContain('v-for="scheduleLabel in courseScheduleLabels(course)"')
        expect(source).not.toContain('course.recurrence_label')
        expect(source).toContain('v-if="course.hours_label"')
        expect(source).toContain('{{ course.hours_label }}')
        expect(source).toContain('v-if="course.instruction_label"')
        expect(source).toContain('{{ course.instruction_label }}')
        expect(source).toMatch(/\.timetable-v3__module-group-cards\s*\{[\s\S]*?display: flex;/)
        expect(source).toMatch(/\.timetable-v3__module-group-card\s*\{[\s\S]*?flex: 1 1 0;/)
        expect(source).toMatch(/\.timetable-v3__module-group-card--active\s*\{[\s\S]*?flex-grow: 1\.5;/)
        expect(source).toContain('transition: flex-grow 170ms ease')
        expect(source).toMatch(/@media \(max-width: 700px\)[\s\S]*?\.timetable-v3__module-group-cards\s*\{[\s\S]*?display: grid;[\s\S]*?grid-template-columns: repeat\(2, minmax\(0, 1fr\)\);/)
        expect(source).toContain('grid-template-columns: repeat(auto-fill, minmax(190px, 1fr))')
        expect(source).toContain('min-height: 60px')
        expect(source).toContain('@media (prefers-reduced-motion: reduce)')
        expect(source).not.toContain('role="tab"')
        expect(source).not.toContain('mdi-chevron-down')
        expect(source).not.toContain('mdi-chevron-up')
        expect(source).not.toContain('toggleModuleGroupCollapse')
        expect(source).not.toContain('@click="openModuleGroup(group)"')
    })

    it('selects and deselects every module in the open module type', async () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const methods = (TimetableV3 as any).methods
        const saveState = vi.fn().mockResolvedValue(undefined)
        const group = {
            key: 'current',
            label: 'Aktuelle',
            modules: [
                {
                    selection_key: 'current:M5',
                    courses: [
                        { key: 'm5-a-1', keys: ['m5-a-1', 'm5-a-2'] },
                        { key: 'm5-b' },
                    ],
                },
                {
                    selection_key: 'current:D5',
                    courses: [{ key: 'd5-a' }],
                },
                {
                    selection_key: 'current:EMPTY',
                    courses: [],
                },
            ],
        }
        const context = {
            selectedModuleKeys: ['finished:D1'],
            selectedCourseKeys: ['d1-a'],
            moduleSelectionGroups: [group],
            moduleSelectionLimitMessage: '',
            selectableModulesForGroup: methods.selectableModulesForGroup,
            moduleSelectionKeysForGroup: methods.moduleSelectionKeysForGroup,
            courseSelectionKeysForGroup: methods.courseSelectionKeysForGroup,
            saveState,
        }

        expect(methods.moduleSelectionKeysForGroup.call(context, group)).toEqual(['current:M5', 'current:D5'])
        expect(methods.courseSelectionKeysForGroup.call(context, group)).toEqual([
            'm5-a-1',
            'm5-a-2',
            'm5-b',
            'd5-a',
        ])
        expect(methods.allModulesSelectedForGroup.call(context, group)).toBe(false)
        expect(methods.hasSelectedModulesForGroup.call(context, group)).toBe(false)

        await methods.selectAllModulesInGroup.call(context, group)

        expect(context.selectedModuleKeys).toEqual(['finished:D1', 'current:M5', 'current:D5'])
        expect(context.selectedCourseKeys).toEqual(['d1-a', 'm5-a-1', 'm5-a-2', 'm5-b', 'd5-a'])
        expect(methods.allModulesSelectedForGroup.call(context, group)).toBe(true)
        expect(methods.hasSelectedModulesForGroup.call(context, group)).toBe(true)
        expect(saveState).toHaveBeenCalledOnce()

        await methods.deselectAllModulesInGroup.call(context, group)

        expect(context.selectedModuleKeys).toEqual(['finished:D1'])
        expect(context.selectedCourseKeys).toEqual(['d1-a'])
        expect(methods.allModulesSelectedForGroup.call(context, group)).toBe(false)
        expect(methods.hasSelectedModulesForGroup.call(context, group)).toBe(false)
        expect(saveState).toHaveBeenCalledTimes(2)
        expect(source).toMatch(/@click="selectAllModulesInGroup\(activeModuleSelectionGroup\)">\s*Alle auswählen/)
        expect(source).toMatch(/variant="text"\s*color="error"[\s\S]*?@click="deselectAllModulesInGroup\(activeModuleSelectionGroup\)">\s*Alle abwählen/)
        expect(source).toContain('@click="selectAllModulesInGroup(activeModuleSelectionGroup)"')
        expect(source).toContain('@click="deselectAllModulesInGroup(activeModuleSelectionGroup)"')
        expect(source).toContain('allModulesSelectedForGroup(activeModuleSelectionGroup)')
        expect(source).toContain('hasSelectedModulesForGroup(activeModuleSelectionGroup)')
    })

    it('limits module selection to ten modules and thirty hours across individual and bulk actions', async () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const methods = (TimetableV3 as any).methods
        const moduleWithCourse = (index: number, hours: number) => ({
            selection_key: `current:M${index}`,
            code: `M${index}`,
            hours,
            courses: [{ key: `m${index}-a` }],
        })
        const tenSelectedModules = Array.from({ length: 10 }, (_, index) => moduleWithCourse(index + 1, 2))
        const eleventhModule = moduleWithCourse(11, 2)
        const moduleLimitSaveState = vi.fn().mockResolvedValue(undefined)
        const moduleLimitContext = {
            moduleSelectionGroups: [{ key: 'current', modules: [...tenSelectedModules, eleventhModule] }],
            selectedModuleKeys: tenSelectedModules.map(module => module.selection_key),
            selectedCourseKeys: tenSelectedModules.map(module => module.courses[0].key),
            moduleCourseDialogModule: eleventhModule,
            moduleCourseDialogCourses: eleventhModule.courses,
            moduleSelectionLimitMessage: '',
            moduleCourseSelected: methods.moduleCourseSelected,
            saveState: moduleLimitSaveState,
        }

        await methods.toggleModuleCourse.call(moduleLimitContext, eleventhModule.courses[0])

        expect(moduleLimitContext.selectedModuleKeys).toHaveLength(10)
        expect(moduleLimitContext.selectedCourseKeys).not.toContain('m11-a')
        expect(moduleLimitContext.moduleSelectionLimitMessage).toBe(
            'Es können höchstens 10 Module gleichzeitig ausgewählt werden.',
        )
        expect(moduleLimitSaveState).not.toHaveBeenCalled()

        const nineSelectedModules = Array.from({ length: 9 }, (_, index) => moduleWithCourse(index + 1, 3))
        const fourHourModule = moduleWithCourse(20, 4)
        const hourLimitContext = {
            moduleSelectionGroups: [{ key: 'current', modules: [...nineSelectedModules, fourHourModule] }],
            selectedModuleKeys: nineSelectedModules.map(module => module.selection_key),
            selectedCourseKeys: nineSelectedModules.map(module => module.courses[0].key),
            moduleCourseDialogModule: fourHourModule,
            moduleCourseDialogCourses: fourHourModule.courses,
            moduleSelectionLimitMessage: '',
            moduleCourseSelected: methods.moduleCourseSelected,
            saveState: vi.fn().mockResolvedValue(undefined),
        }

        await methods.toggleModuleCourse.call(hourLimitContext, fourHourModule.courses[0])

        expect(hourLimitContext.selectedModuleKeys).toHaveLength(9)
        expect(hourLimitContext.selectedCourseKeys).not.toContain('m20-a')
        expect(hourLimitContext.moduleSelectionLimitMessage).toBe(
            'Es können höchstens 30 Stunden gleichzeitig ausgewählt werden.',
        )
        expect(hourLimitContext.saveState).not.toHaveBeenCalled()

        const thirtyHourModule = moduleWithCourse(21, 3)
        const boundaryContext = {
            moduleSelectionGroups: [{ key: 'current', modules: [...nineSelectedModules, thirtyHourModule] }],
            selectedModuleKeys: nineSelectedModules.map(module => module.selection_key),
            selectedCourseKeys: nineSelectedModules.map(module => module.courses[0].key),
            moduleCourseDialogModule: thirtyHourModule,
            moduleCourseDialogCourses: thirtyHourModule.courses,
            moduleSelectionLimitMessage: '',
            moduleCourseSelected: methods.moduleCourseSelected,
            saveState: vi.fn().mockResolvedValue(undefined),
        }

        await methods.toggleModuleCourse.call(boundaryContext, thirtyHourModule.courses[0])

        expect(boundaryContext.selectedModuleKeys).toHaveLength(10)
        expect(boundaryContext.selectedCourseKeys).toContain('m21-a')
        expect(boundaryContext.moduleSelectionLimitMessage).toBe('')
        expect(boundaryContext.saveState).toHaveBeenCalledOnce()

        const bulkModules = [moduleWithCourse(30, 2), moduleWithCourse(31, 2)]
        const bulkGroup = { key: 'additional', modules: bulkModules }
        const bulkContext = {
            moduleSelectionGroups: [
                { key: 'current', modules: nineSelectedModules },
                bulkGroup,
            ],
            selectedModuleKeys: nineSelectedModules.map(module => module.selection_key),
            selectedCourseKeys: nineSelectedModules.map(module => module.courses[0].key),
            moduleSelectionLimitMessage: '',
            selectableModulesForGroup: methods.selectableModulesForGroup,
            moduleSelectionKeysForGroup: methods.moduleSelectionKeysForGroup,
            courseSelectionKeysForGroup: methods.courseSelectionKeysForGroup,
            saveState: vi.fn().mockResolvedValue(undefined),
        }

        await methods.selectAllModulesInGroup.call(bulkContext, bulkGroup)

        expect(bulkContext.selectedModuleKeys).toHaveLength(9)
        expect(bulkContext.selectedCourseKeys).not.toContain('m30-a')
        expect(bulkContext.moduleSelectionLimitMessage).toBe(
            'Es können höchstens 10 Module gleichzeitig ausgewählt werden.',
        )
        expect(bulkContext.saveState).not.toHaveBeenCalled()
        expect(source).toContain('Maximal {{ maximumSelectedModules }} Module und')
        expect(source).toContain('{{ maximumSelectedModuleHours }} Stunden gleichzeitig.')
        expect(source).toContain('{{ selectedModuleCount }}/{{ maximumSelectedModules }} Module')
        expect(source).toContain('{{ selectedModuleHoursLabel }}/{{ maximumSelectedModuleHours }} Std.')
        expect(source.match(/v-if="moduleSelectionLimitMessage"/g)).toHaveLength(2)
    })

    it('removes a selected module from the automatic mode summary', async () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const methods = (TimetableV3 as any).methods
        const saveState = vi.fn().mockResolvedValue(undefined)
        const context = {
            selectedModuleKeys: ['finished:D1', 'current:M5'],
            selectedCourseKeys: ['d1-a', 'm5-a-1', 'm5-a-2', 'm5-b'],
            saveState,
        }
        const module = {
            selection_key: 'current:M5',
            code: 'M5',
            courses: [
                { key: 'm5-a-1', keys: ['m5-a-1', 'm5-a-2'] },
                { key: 'm5-b' },
            ],
        }

        await methods.removeSelectedModule.call(context, module)

        expect(context.selectedModuleKeys).toEqual(['finished:D1'])
        expect(context.selectedCourseKeys).toEqual(['d1-a'])
        expect(saveState).toHaveBeenCalledOnce()
        expect(source).toContain('class="timetable-v3__selected-module-chip"')
        expect(source).toContain('close-icon="mdi-close-circle"')
        expect(source).toContain(':close-label="`${module.code} aus der Auswahl entfernen`"')
        expect(source).toContain('@click:close.stop="removeSelectedModule(module)"')
        expect(source).toMatch(/\.timetable-v3__selected-module-chip :deep\(\.v-chip__close\)[\s\S]*?color: #dc2626;/)
    })

    it('deselects every module from the automatic mode card', async () => {
        const methods = (TimetableV3 as any).methods
        const saveState = vi.fn().mockResolvedValue(undefined)
        const context = {
            selectedModuleKeys: ['finished:D1', 'current:M5'],
            selectedCourseKeys: ['d1-a', 'm5-a-1', 'm5-a-2', 'm5-b'],
            moduleSelectionLimitMessage: 'Auswahlgrenze erreicht.',
            saveState,
        }

        await methods.deselectAllSelectedModules.call(context)

        expect(context.selectedModuleKeys).toEqual([])
        expect(context.selectedCourseKeys).toEqual([])
        expect(context.moduleSelectionLimitMessage).toBe('')
        expect(saveState).toHaveBeenCalledOnce()

        await methods.deselectAllSelectedModules.call(context)

        expect(saveState).toHaveBeenCalledOnce()
    })

    it('restores only persisted course selections from the matching planning context', () => {
        const methods = (TimetableV3 as any).methods
        const groups = [{
            key: 'current',
            modules: [{
                selection_key: 'current:D5',
                code: 'D5',
                courses: [
                    { key: 'd5-a-1', keys: ['d5-a-1', 'd5-a-2'], title: 'D5 - 3A - HU' },
                    { key: 'd5-b', title: 'D5 - 3B - KO' },
                ],
            }],
        }]
        const context = {
            storedState: {
                moduleSelection: {
                    mode: 'with_student',
                    studentCode: '1001',
                    planningValues: {
                        branch: null,
                        language: 'L',
                        religion: 'ETH',
                        arts_subject: null,
                    },
                    scheduleCreationMode: 'automatic',
                    selectedKeys: ['current:D5'],
                    selectedCourseKeys: ['d5-a-2', 'missing-course'],
                },
            },
            planningSelectionValues: {
                religion: 'ETH',
                language: 'L',
                branch: null,
                arts_subject: null,
            },
            moduleSelectionGroups: [],
            selectedModuleKeys: [],
            selectedCourseKeys: [],
            scheduleCreationMode: null,
            activeModuleGroupKey: '',
            moduleSelectionResetPending: false,
        }

        methods.setModuleSelectionGroups.call(context, groups, '1001')

        expect(context.selectedCourseKeys).toEqual(['d5-a-1', 'd5-a-2'])
        expect(context.selectedModuleKeys).toEqual(['current:D5'])
        expect(context.scheduleCreationMode).toBe('automatic')
    })

    it('constrains an oversized persisted module selection while restoring it', () => {
        const methods = (TimetableV3 as any).methods
        const modules = Array.from({ length: 11 }, (_, index) => ({
            selection_key: `current:M${index + 1}`,
            code: `M${index + 1}`,
            hours: 3,
            courses: [{ key: `m${index + 1}-a` }],
        }))
        const groups = [{ key: 'current', modules }]
        const context = {
            storedState: {
                moduleSelection: {
                    mode: 'with_student',
                    studentCode: '1001',
                    planningValues: {},
                    selectedKeys: modules.map(module => module.selection_key),
                    selectedCourseKeys: modules.map(module => module.courses[0].key),
                },
            },
            planningSelectionValues: {},
            moduleSelectionGroups: [],
            selectedModuleKeys: [],
            selectedCourseKeys: [],
            moduleSelectionLimitMessage: '',
            scheduleCreationMode: null,
            activeModuleGroupKey: '',
            moduleSelectionResetPending: false,
        }

        methods.setModuleSelectionGroups.call(context, groups, '1001')

        expect(context.selectedModuleKeys).toEqual(modules.slice(0, 10).map(module => module.selection_key))
        expect(context.selectedCourseKeys).toEqual(modules.slice(0, 10).map(module => module.courses[0].key))
        expect(context.moduleSelectionLimitMessage).toBe(
            'Die gespeicherte Auswahl wurde auf maximal 10 Module und 30 Stunden begrenzt.',
        )
    })

    it('keeps both entry choices editable and offers a persistent V3 restart action', async () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const methods = (TimetableV3 as any).methods
        const context = {
            planningMode: 'with_student',
            selectedStudent: { studentCode: '1001' },
            studentDialogOpen: true,
            studentSearch: 'Muster',
            emailCopyStatus: 'copied',
            studentInfoDialogOpen: true,
            studyInfoDialogOpen: true,
            timetableFilters: { include_saturday: false, free_days: null },
            workspaceId: 'workspace-1',
            resetSelectedStudentSelectionDetails: vi.fn(),
            resetTimetableCalculation: vi.fn(),
            saveState: vi.fn().mockResolvedValue(undefined),
            $router: { replace: vi.fn().mockResolvedValue(undefined) },
        }

        await methods.restartPlanning.call(context)

        expect(source).toContain('@click="chooseWithStudent"')
        expect(source).toContain('@click="chooseWithoutStudent"')
        const selectionStepSource = source.slice(0, source.indexOf('<div v-else'))

        expect(selectionStepSource).not.toContain('class="timetable-v3__back-button"')
        expect(selectionStepSource).not.toContain('prepend-icon="mdi-arrow-left"')
        expect(selectionStepSource).not.toContain('Zurück')
        expect(source.match(/class="timetable-v3__restart-button"/g)).toHaveLength(4)
        expect(source.match(/prepend-icon="mdi-restart"/g)).toHaveLength(4)
        expect(source.match(/@click="restartPlanning"/g)).toHaveLength(4)
        expect(source.match(/Neustart/g)).toHaveLength(4)
        expect(context.planningMode).toBeNull()
        expect(context.selectedStudent).toBeNull()
        expect(context.studentDialogOpen).toBe(false)
        expect(context.studentSearch).toBe('')
        expect(context.emailCopyStatus).toBe('idle')
        expect(context.studentInfoDialogOpen).toBe(false)
        expect(context.studyInfoDialogOpen).toBe(false)
        expect(context.timetableFilters).toEqual({ include_saturday: true, free_days: null })
        expect(context.resetSelectedStudentSelectionDetails).toHaveBeenCalledOnce()
        expect(context.resetTimetableCalculation).toHaveBeenCalledOnce()
        expect(context.saveState).toHaveBeenCalledOnce()
        expect(context.$router.replace).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v3/overview',
            query: { workspace_id: 'workspace-1' },
        })
    })

    it('formats student choices without semester information', () => {
        const methods = (TimetableV3 as any).methods
        const label = methods.studentDisplayLabel({
            class: '5A',
            last_name: 'Muster',
            first_name: 'Mia',
            school_level: '12',
            attendance_year: '6',
        })

        expect(label).toBe('5A · Muster Mia')
        expect(label).not.toContain('Semester')
        expect(label).not.toContain('12')
        expect(label).not.toContain('6')
    })

    it('selects a student immediately and stores only the V3 entry context', async () => {
        const methods = (TimetableV3 as any).methods
        const context = {
            planningMode: null,
            selectedStudent: null,
            selectedStudentCode: '1001',
            closeStudentDialog: vi.fn(),
            resetSelectedStudentSelectionDetails: vi.fn(),
            normalizedStudentSex: methods.normalizedStudentSex,
            normalizedStudentSemester: methods.normalizedStudentSemester,
            loadSelectedStudentSelection: vi.fn(),
            saveState: vi.fn().mockResolvedValue(undefined),
            replaceRoutePlanningContext: vi.fn(),
        }

        await methods.selectStudent.call(context, {
            id: 7,
            student_code: '1001',
            class: '5A',
            last_name: 'Muster',
            first_name: 'Mia',
            email: 'mia@example.test',
            sex: 'w',
            religion: 'Rk',
            instruction_type: 'Normalunterricht',
            semester: 5,
            school_level: '12',
            attendance_year: '6',
        })

        expect(context.planningMode).toBe('with_student')
        expect(context.selectedStudent).toEqual({
            id: 7,
            studentCode: '1001',
            className: '5A',
            lastName: 'Muster',
            firstName: 'Mia',
            email: 'mia@example.test',
            sex: 'w',
            religion: 'Rk',
            instructionType: 'Normalunterricht',
            semester: 5,
            schoolLevel: '12',
        })
        expect(context.closeStudentDialog).toHaveBeenCalledOnce()
        expect(context.resetSelectedStudentSelectionDetails).toHaveBeenCalledOnce()
        expect(context.loadSelectedStudentSelection).toHaveBeenCalledOnce()
        expect(context.saveState).toHaveBeenCalledOnce()
        expect(context.replaceRoutePlanningContext).toHaveBeenCalledWith({
            planning_mode: 'with_student',
            student_code: '1001',
        })
    })

    it('switches to planning without a student and clears the selected person', async () => {
        const methods = (TimetableV3 as any).methods
        const context = {
            planningMode: 'with_student',
            selectedStudent: { studentCode: '1001' },
            resetSelectedStudentSelectionDetails: vi.fn(),
            saveState: vi.fn().mockResolvedValue(undefined),
            loadSelectedStudentSelection: vi.fn(),
            replaceRoutePlanningContext: vi.fn(),
        }

        await methods.chooseWithoutStudent.call(context)

        expect(context.planningMode).toBe('without_student')
        expect(context.selectedStudent).toBeNull()
        expect(context.resetSelectedStudentSelectionDetails).toHaveBeenCalledOnce()
        expect(context.saveState).toHaveBeenCalledOnce()
        expect(context.loadSelectedStudentSelection).toHaveBeenCalledOnce()
        expect(context.replaceRoutePlanningContext).toHaveBeenCalledWith({
            planning_mode: 'without_student',
            student_code: null,
        })
    })

    it('persists the selection through the separate V3 state endpoint', async () => {
        const methods = (TimetableV3 as any).methods
        const put = vi.fn().mockResolvedValue({
            data: {
                data: {
                    state: {
                        retained: true,
                        entrySelection: {
                            mode: 'without_student',
                            student: null,
                        },
                    },
                },
            },
        })
        vi.stubGlobal('axios', { put })
        const context = {
            isSavingState: false,
            stateSaveFailed: false,
            storedState: { retained: true },
            workspaceId: WORKSPACE_ID,
            planningMode: 'without_student',
            selectedStudent: null,
            selectedStudentCode: '',
            planningSelectionValues: {
                religion: 'ETH',
                language: 'F',
            },
            selectedModuleKeys: ['current:D5'],
            selectedCourseKeys: ['d5-a'],
            scheduleCreationMode: 'automatic',
            timetableAdoptionReturnStep: 'modules',
            timetableFilters: { include_saturday: false, free_days: 2 },
        }

        await methods.saveState.call(context)

        expect(put).toHaveBeenCalledWith('/api/admin/students-timetables/timetable-v3-state', {
            context: {
                workspace_id: WORKSPACE_ID,
                planning_mode: 'without_student',
                student_code: null,
            },
            state: {
                retained: true,
                adoptionReturnStep: 'modules',
                entrySelection: {
                    mode: 'without_student',
                    student: null,
                },
                planningSelection: {
                    mode: 'without_student',
                    studentCode: null,
                    values: {
                        religion: 'ETH',
                        language: 'F',
                    },
                },
                moduleSelection: {
                    mode: 'without_student',
                    studentCode: null,
                    planningValues: {
                        religion: 'ETH',
                        language: 'F',
                    },
                    scheduleCreationMode: 'automatic',
                    selectedKeys: ['current:D5'],
                    selectedCourseKeys: ['d5-a'],
                },
                creationOptions: {
                    filters: {
                        include_saturday: false,
                        free_days: 2,
                    },
                },
            },
        })
        expect(context.isSavingState).toBe(false)
        expect(context.stateSaveFailed).toBe(false)
    })

    it('serializes V3 state writes so an older snapshot cannot overwrite the latest selection', async () => {
        const methods = (TimetableV3 as any).methods
        let resolveFirstSave: (() => void) | undefined
        let resolveSecondSave: (() => void) | undefined
        const put = vi.fn()
            .mockImplementationOnce((_url, payload) => new Promise((resolve) => {
                resolveFirstSave = () => resolve({ data: { data: { state: payload.state } } })
            }))
            .mockImplementationOnce((_url, payload) => new Promise((resolve) => {
                resolveSecondSave = () => resolve({ data: { data: { state: payload.state } } })
            }))
        vi.stubGlobal('axios', { put })
        const context = {
            isSavingState: false,
            pendingStateSaveCount: 0,
            stateSaveQueue: null,
            stateSaveFailed: false,
            storedState: {},
            workspaceId: WORKSPACE_ID,
            planningMode: 'without_student',
            selectedStudent: null,
            selectedStudentCode: '',
            planningSelectionValues: {},
            selectedModuleKeys: [],
            selectedCourseKeys: [],
            scheduleCreationMode: 'automatic',
            timetableFilters: { include_saturday: false, free_days: null },
        }

        const firstSave = methods.saveState.call(context)
        await vi.waitFor(() => expect(put).toHaveBeenCalledTimes(1))

        context.selectedModuleKeys = ['current:D5']
        context.selectedCourseKeys = ['d5-a']
        const secondSave = methods.saveState.call(context)

        expect(context.isSavingState).toBe(true)
        expect(put).toHaveBeenCalledTimes(1)

        resolveFirstSave?.()
        await firstSave
        await vi.waitFor(() => expect(put).toHaveBeenCalledTimes(2))

        expect(put.mock.calls[1][1].state.moduleSelection.selectedKeys).toEqual(['current:D5'])
        expect(put.mock.calls[1][1].state.moduleSelection.selectedCourseKeys).toEqual(['d5-a'])

        resolveSecondSave?.()
        await secondSave

        expect(context.storedState.moduleSelection.selectedKeys).toEqual(['current:D5'])
        expect(context.storedState.moduleSelection.selectedCourseKeys).toEqual(['d5-a'])
        expect(context.isSavingState).toBe(false)
        expect(context.pendingStateSaveCount).toBe(0)
    })
})
