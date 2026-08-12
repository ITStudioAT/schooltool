import { readFileSync } from 'node:fs'
import { afterEach, describe, expect, it, vi } from 'vitest'
import TimetableV3 from '@/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue'

describe('TimetableV3', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
    })

    it('offers both intuitive planning modes without a V2 shortcut', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        expect(source).toContain('StudentTimetableV3StateController')
        expect(source).toContain('showTimetableV3State.url()')
        expect(source).toContain('updateTimetableV3State.url()')
        expect(source).toContain('loadRobotStudents.url()')
        expect(source).toContain('StudentTimetableV3StudentInformationController')
        expect(source).toContain('loadV3StudentInformation.url({')
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
        expect(source).toContain('<span v-else>Ohne Studierenden</span>')
        expect(source).toContain('v-if="selectedStudentEmail"')
        expect(source).toContain('@click="copySelectedStudentEmail"')
        expect(source).toContain('timetable-v3__email-row')
        expect(source).toContain('timetable-v3__email-copy-button')
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
        expect(studentInfoDialogSource).toContain('Semester')
        expect(studentInfoDialogSource).toContain('selectedStudentSemesterLabel')
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
        })
        expect(context.saveState).toHaveBeenCalledOnce()
    })

    it('shows the calculated semester in the student information dialog', () => {
        const methods = (TimetableV3 as any).methods
        const semesterLabel = (TimetableV3 as any).computed.selectedStudentSemesterLabel

        expect(methods.normalizedStudentSemester({ semester: '5' })).toBe(5)
        expect(methods.normalizedStudentSemester({ semester: null })).toBeNull()
        expect(semesterLabel.call({
            selectedStudent: { semester: 5 },
            studentSelectionItems: [],
            normalizedStudentSemester: methods.normalizedStudentSemester,
        })).toBe('5. Semester')
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
        const get = vi.fn().mockResolvedValue({
            data: {
                data: {
                    religion: 'Rk',
                    instruction_type: 'Kompaktunterricht',
                    semester: 5,
                    items: selectionItems,
                    selection_fields: selectionFields,
                    module_groups: moduleGroups,
                    module_selection_groups: moduleSelectionGroups,
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
            setPlanningSelectionFields: methods.setPlanningSelectionFields,
            setModuleSelectionGroups: methods.setModuleSelectionGroups,
        }

        await methods.loadSelectedStudentSelection.call(context)

        expect(get).toHaveBeenCalledWith(
            '/api/admin/students-timetables/timetable-v3/student-information?student_code=1001',
        )
        expect(context.studentSelectionItems).toEqual(selectionItems)
        expect(context.studentStudyModuleGroups).toEqual(moduleGroups)
        expect(context.studentStudyModuleGroups[1].modules[0].name).toBe('Deutsch 1')
        expect(context.moduleSelectionGroups).toEqual(moduleSelectionGroups)
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

        const continueButtonStart = source.indexOf('class="timetable-v3__selection-continue-button"')
        const continueButtonEnd = source.indexOf('</v-btn>', continueButtonStart)
        const continueButtonSource = source.slice(continueButtonStart, continueButtonEnd)
        const selectionStepSource = source.slice(0, source.indexOf('<div v-else'))

        expect(hasPlanningSelectionContext.call({ planningMode: null, selectedStudentCode: '' })).toBe(false)
        expect(hasPlanningSelectionContext.call({ planningMode: 'with_student', selectedStudentCode: '' })).toBe(false)
        expect(hasPlanningSelectionContext.call({ planningMode: 'with_student', selectedStudentCode: '1001' })).toBe(true)
        expect(hasPlanningSelectionContext.call({ planningMode: 'without_student', selectedStudentCode: '' })).toBe(true)
        expect(source).toContain('v-if="hasPlanningSelectionContext"')
        expect(source).toContain(':disabled="!hasPlanningSelectionContext || isLoadingState || isSavingState"')
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
            $router: { push },
        }

        methods.continueToNextStep.call(context)
        expect(push).not.toHaveBeenCalled()

        context.hasPlanningSelectionContext = true
        methods.continueToNextStep.call(context)
        expect(push).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v3/modules',
        })

        methods.returnToSelectionStep.call(context)
        expect(push).toHaveBeenLastCalledWith({
            path: '/admin/students-timetables/timetable-v3/overview',
        })
        expect(currentStep.call({ $route: { params: { subsection: 'overview' } } })).toBe('selection')
        expect(currentStep.call({ $route: { params: { subsection: 'modules' } } })).toBe('modules')

        const nextStepStart = source.indexOf('<div v-else class="timetable-v3__step timetable-v3__next-step">')
        const nextStepEnd = source.indexOf('<v-dialog', nextStepStart)
        const nextStepSource = source.slice(nextStepStart, nextStepEnd)
        const nextContinueStart = nextStepSource.indexOf('class="timetable-v3__next-continue-button"')
        const nextContinueEnd = nextStepSource.indexOf('</v-btn>', nextContinueStart)
        const nextContinueSource = nextStepSource.slice(nextContinueStart, nextContinueEnd)

        expect(nextStepSource).toContain('Aktuelle Auswahl')
        expect(nextStepSource).toContain("v-if=\"planningMode === 'with_student'\"")
        expect(nextStepSource).toContain("{{ selectedStudentClass || '–' }} · {{ selectedStudentFullName || selectedStudentLabel }}")
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
        expect(nextStepSource).not.toContain('timetable-v3__compact-planning-title')
        expect(nextStepSource).not.toContain('mdi-tune-variant')
        expect(nextStepSource).not.toContain('Automatik')
        expect(nextStepSource).not.toContain('Manuell')
        expect(nextStepSource).not.toContain('chooseCreationMode')
        expect(nextStepSource).toContain('Welche Module sollen berücksichtigt werden?')
        expect(nextStepSource).toContain('v-for="group in displayedModuleSelectionGroups"')
        expect(nextStepSource).toContain('class="timetable-v3__module-tile"')
        expect(nextStepSource).toContain('@click="openModuleCoursesDialog(module)"')
        expect(nextStepSource).not.toContain('role="tab"')
        expect(nextStepSource).toContain('class="timetable-v3__back-button"')
        expect(nextStepSource).toContain('prepend-icon="mdi-arrow-left"')
        expect(nextStepSource).toContain('@click="returnToSelectionStep"')
        expect(nextStepSource).toContain('Zurück')
        expect(nextContinueSource).toContain('Weiter')
        expect(nextContinueSource).not.toContain('@click')
    })

    it('opens a persistent course dialog from an individual module tile', async () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const methods = (TimetableV3 as any).methods
        const displayedModuleSelectionGroups = (TimetableV3 as any).computed.displayedModuleSelectionGroups
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
            moduleCoursesDialogOpen: false,
            moduleCourseDialogModule: null,
            moduleCourseDialogCourses: groups[1].modules[0].courses,
            moduleCourseSelected: methods.moduleCourseSelected,
            selectedCourseCountForModule: methods.selectedCourseCountForModule,
            saveState,
        }
        const groupContext = {
            activeModuleGroupKey: '',
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
            display_schedule_labels: ['Montag · 20:25–21:55 · 1-wöchig'],
            schedule_labels: [
                'Montag · 20:25–21:10 · 1-wöchig',
                'Montag · 21:10–21:55 · 1-wöchig',
            ],
        })).toEqual(['Montag · 20:25–21:55 · 1-wöchig'])
        methods.closeModuleCoursesDialog.call(context)
        expect(context.moduleCoursesDialogOpen).toBe(false)
        methods.toggleModuleGroup.call(groupContext, groups[0])
        expect(groupContext.activeModuleGroupKey).toBe('finished')
        expect(methods.moduleGroupActive.call(groupContext, groups[0])).toBe(true)
        methods.toggleModuleGroup.call(groupContext, groups[0])
        expect(groupContext.activeModuleGroupKey).toBe('')
        expect(methods.moduleGroupActive.call(groupContext, groups[0])).toBe(false)
        expect(methods.moduleGroupIcon(groups[0])).toBe('mdi-check-decagram-outline')
        expect(methods.moduleGroupIcon({ key: 'unknown' })).toBe('mdi-view-grid-outline')
        const displayedGroups = displayedModuleSelectionGroups.call({
            moduleSearch: 'deutsch',
            moduleSelectionGroups: groups,
        })
        expect(displayedGroups).toEqual([
            { ...groups[0], modules: groups[0].modules },
            { ...groups[1], modules: [] },
        ])
        expect(activeModuleSelectionGroup.call({
            activeModuleGroupKey: 'finished',
            displayedModuleSelectionGroups: displayedGroups,
        })).toEqual(displayedGroups[0])
        expect(activeModuleSelectionGroup.call({
            activeModuleGroupKey: '',
            displayedModuleSelectionGroups: displayedGroups,
        })).toBeNull()
        expect(source).toContain('v-for="group in displayedModuleSelectionGroups"')
        expect(source).toContain('class="timetable-v3__module-group-card"')
        expect(source).toContain('class="timetable-v3__module-group-panel"')
        expect(source).toContain('@click="toggleModuleGroup(group)"')
        expect(source).toContain('v-if="activeModuleSelectionGroup"')
        expect(source).toContain('<transition name="timetable-v3-module-panel" mode="out-in">')
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
        expect(source).toContain('Alle auswählen')
        expect(source).toContain('Alle abwählen')
        expect(source).toContain('v-for="scheduleLabel in courseScheduleLabels(course)"')
        expect(source).not.toContain('course.recurrence_label')
        expect(source).toContain('v-if="course.hours_label"')
        expect(source).toContain('{{ course.hours_label }}')
        expect(source).toContain('v-if="course.instruction_label"')
        expect(source).toContain('{{ course.instruction_label }}')
        expect(source).toContain('class="timetable-v3__module-search mt-3"')
        expect(source).toContain('grid-template-columns: repeat(auto-fit, minmax(142px, 1fr))')
        expect(source).toContain('grid-template-columns: repeat(auto-fill, minmax(190px, 1fr))')
        expect(source).toContain('min-height: 60px')
        expect(source).toContain('@media (prefers-reduced-motion: reduce)')
        expect(source).not.toContain('role="tab"')
        expect(source).not.toContain('mdi-chevron-down')
        expect(source).not.toContain('mdi-chevron-up')
        expect(source).not.toContain('toggleModuleGroupCollapse')
        expect(source).not.toContain('@click="openModuleGroup(group)"')
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
                    planningValues: { language: 'L' },
                    selectedKeys: ['current:D5'],
                    selectedCourseKeys: ['d5-a-2', 'missing-course'],
                },
            },
            planningSelectionValues: { language: 'L' },
            moduleSelectionGroups: [],
            selectedModuleKeys: [],
            selectedCourseKeys: [],
            activeModuleGroupKey: '',
            moduleSelectionResetPending: false,
        }

        methods.setModuleSelectionGroups.call(context, groups, '1001')

        expect(context.selectedCourseKeys).toEqual(['d5-a-1', 'd5-a-2'])
        expect(context.selectedModuleKeys).toEqual(['current:D5'])
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
            resetSelectedStudentSelectionDetails: vi.fn(),
            saveState: vi.fn().mockResolvedValue(undefined),
        }

        await methods.restartPlanning.call(context)

        expect(source).toContain('@click="chooseWithStudent"')
        expect(source).toContain('@click="chooseWithoutStudent"')
        const selectionStepSource = source.slice(0, source.indexOf('<div v-else'))

        expect(selectionStepSource).not.toContain('class="timetable-v3__back-button"')
        expect(selectionStepSource).not.toContain('prepend-icon="mdi-arrow-left"')
        expect(selectionStepSource).not.toContain('Zurück')
        expect(source).toContain('class="timetable-v3__restart-button"')
        expect(source).toContain('color="error"')
        expect(source).toContain('prepend-icon="mdi-restart"')
        expect(source).toContain('@click="restartPlanning"')
        expect(source).toContain('Neustart')
        expect(context.planningMode).toBeNull()
        expect(context.selectedStudent).toBeNull()
        expect(context.studentDialogOpen).toBe(false)
        expect(context.studentSearch).toBe('')
        expect(context.emailCopyStatus).toBe('idle')
        expect(context.studentInfoDialogOpen).toBe(false)
        expect(context.studyInfoDialogOpen).toBe(false)
        expect(context.resetSelectedStudentSelectionDetails).toHaveBeenCalledOnce()
        expect(context.saveState).toHaveBeenCalledOnce()
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
            closeStudentDialog: vi.fn(),
            resetSelectedStudentSelectionDetails: vi.fn(),
            normalizedStudentSex: methods.normalizedStudentSex,
            normalizedStudentSemester: methods.normalizedStudentSemester,
            loadSelectedStudentSelection: vi.fn(),
            saveState: vi.fn().mockResolvedValue(undefined),
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
        })
        expect(context.closeStudentDialog).toHaveBeenCalledOnce()
        expect(context.resetSelectedStudentSelectionDetails).toHaveBeenCalledOnce()
        expect(context.loadSelectedStudentSelection).toHaveBeenCalledOnce()
        expect(context.saveState).toHaveBeenCalledOnce()
    })

    it('switches to planning without a student and clears the selected person', async () => {
        const methods = (TimetableV3 as any).methods
        const context = {
            planningMode: 'with_student',
            selectedStudent: { studentCode: '1001' },
            resetSelectedStudentSelectionDetails: vi.fn(),
            saveState: vi.fn().mockResolvedValue(undefined),
            loadSelectedStudentSelection: vi.fn(),
        }

        await methods.chooseWithoutStudent.call(context)

        expect(context.planningMode).toBe('without_student')
        expect(context.selectedStudent).toBeNull()
        expect(context.resetSelectedStudentSelectionDetails).toHaveBeenCalledOnce()
        expect(context.saveState).toHaveBeenCalledOnce()
        expect(context.loadSelectedStudentSelection).toHaveBeenCalledOnce()
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
            planningMode: 'without_student',
            selectedStudent: null,
            selectedStudentCode: '',
            planningSelectionValues: {
                religion: 'ETH',
                language: 'F',
            },
            selectedModuleKeys: ['current:D5'],
            selectedCourseKeys: ['d5-a'],
        }

        await methods.saveState.call(context)

        expect(put).toHaveBeenCalledWith('/api/admin/students-timetables/timetable-v3-state', {
            state: {
                retained: true,
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
                    selectedKeys: ['current:D5'],
                    selectedCourseKeys: ['d5-a'],
                },
            },
        })
        expect(context.isSavingState).toBe(false)
        expect(context.stateSaveFailed).toBe(false)
    })
})
