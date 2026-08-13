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
        expect(source).toContain('class="timetable-v3__selection-religion"')
        expect(source).toContain('· {{ selectedStudentReligion }}')
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
        expect(source).toContain('v-if="planningSelectionFields.length && !studentSelectionDetailsError"')
        expect(source).toContain(':disabled="studentSelectionDetailsLoading || isSavingState"')
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
        expect(currentStep.call({ $route: { params: { subsection: 'creation' } } })).toBe('creation')

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
        expect(nextStepSource).not.toContain('timetable-v3__compact-planning-title')
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
        const manualCardEndPosition = source.indexOf('</label>', manualCardPosition)
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
        expect(source).toContain('Verfügbare Module und Unterrichte')
        expect(source).toContain('Alle Module und Unterrichte stehen zur Verfügung.')
        expect(source).toContain('Sie wählen die Module. Das System erstellt und optimiert daraus den Stundenplan.')
        expect(source).toContain('class="timetable-v3__schedule-mode-recommendation"')
        expect(source).toContain('mdi-star-four-points')
        expect(source).toContain('Empfohlen')
        expect(source).toContain('background: linear-gradient(135deg, #fef08a, #facc15)')
        expect(source).toContain(
            'Sie stellen den Stundenplan selbst zusammen und platzieren die Unterrichte manuell.',
        )
        expect(source).toContain("scheduleCreationMode === 'automatic' ? 'Ausgewählt' : 'Automatisch wählen'")
        expect(source).toContain("scheduleCreationMode === 'manual' ? 'Ausgewählt' : 'Manuell wählen'")
        expect(source).toContain('role="radiogroup"')
        expect(source).toContain('type="radio"')
        expect(source).toContain('name="timetable-v3-schedule-mode"')
        expect(source).toContain(':checked="scheduleCreationMode === \'automatic\'"')
        expect(source).toContain(':checked="scheduleCreationMode === \'manual\'"')
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
        expect(source).toMatch(/\.timetable-v3__schedule-create-action\s*\{[\s\S]*?justify-content: flex-end;/)
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

    it('opens a static timetable creation page with a read-only module summary and creation options', async () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const methods = (TimetableV3 as any).methods
        const currentStepWatcher = (TimetableV3 as any).watch.currentStep
        const push = vi.fn()
        const context = {
            scheduleCreationMode: 'automatic',
            selectedModuleCount: 0,
            isSavingState: false,
            stateSaveFailed: false,
            timetableCalculationStatus: 'idle',
            saveState: vi.fn().mockResolvedValue(undefined),
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
        expect(push).toHaveBeenCalledWith({
            path: '/admin/students-timetables/timetable-v3/creation',
        })

        context.scheduleCreationMode = 'manual'
        await methods.openTimetableCreationPage.call(context)
        expect(push).toHaveBeenCalledTimes(1)

        methods.returnFromTimetableCreationStep.call(context)
        expect(push).toHaveBeenLastCalledWith({
            path: '/admin/students-timetables/timetable-v3/modules',
        })

        context.timetableCalculationStatus = 'success'
        methods.returnFromTimetableCreationStep.call(context)
        expect(context.resetTimetableCalculation).toHaveBeenCalledOnce()
        expect(push).toHaveBeenCalledTimes(2)

        context.scheduleCreationMode = 'automatic'
        currentStepWatcher.call(context, 'modules', 'creation')
        expect(context.scheduleCreationMode).toBe('automatic')
        expect(context.resetTimetableCalculation).toHaveBeenCalledTimes(2)

        const creationPageStart = source.indexOf(
            '<template v-else>\n            <div v-if="timetableCalculationStatus === \'idle\'"',
        )
        const creationPageEnd = source.indexOf('\n        </div>\n\n        <v-dialog', creationPageStart)
        const creationPageSource = source.slice(creationPageStart, creationPageEnd)
        const optionsCardStart = creationPageSource.indexOf('timetable-v3__creation-options-card')
        const optionsCardEnd = creationPageSource.indexOf('</section>', optionsCardStart)
        const optionsCardSource = creationPageSource.slice(optionsCardStart, optionsCardEnd)

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
        expect(optionsCardSource).toContain('Optionen')
        expect(optionsCardSource).toContain('Samstags Unterricht?')
        expect(optionsCardSource).toContain(':model-value="allowSaturdayLessons"')
        expect(optionsCardSource).toContain('@update:model-value="updateAllowSaturdayLessons"')
        expect(optionsCardSource).toContain("allowSaturdayLessons ? 'Ja' : 'Nein'")
        expect(optionsCardSource).toContain('class="timetable-v3__creation-start-button"')
        expect(optionsCardSource).toContain('@click="calculatePossibleTimetables"')
        expect(optionsCardSource).toContain('Los!')
        expect(optionsCardSource).not.toContain('Manueller Stundenplan')
        expect(optionsCardSource).not.toContain('Verfügbare Module und Unterrichte')
        expect(creationPageSource).toContain('@click="returnFromTimetableCreationStep"')
        expect(creationPageSource).toContain('prepend-icon="mdi-arrow-left"')
        expect(creationPageSource).toContain('Zurück')
        expect(data.allowSaturdayLessons).toBe(false)
        const optionContext = {
            allowSaturdayLessons: false,
            saveState: vi.fn().mockResolvedValue(undefined),
        }
        await methods.updateAllowSaturdayLessons.call(optionContext, true)
        expect(optionContext.allowSaturdayLessons).toBe(true)
        expect(optionContext.saveState).toHaveBeenCalledOnce()
        expect(source).toContain('creationOptions: {')
        expect(source).toContain('allowSaturdayLessons: this.allowSaturdayLessons')
        expect(source).toMatch(/\.timetable-v3__creation-summary-card\s*\{[\s\S]*?animation: none;[\s\S]*?transition: none;/)
        expect(source).toMatch(/\.timetable-v3__creation-summary-card:hover,[\s\S]*?transform: none;/)
    })

    it('saves the creation options before requesting possible timetable variants and ignores a double click', async () => {
        const methods = (TimetableV3 as any).methods
        let resolveStateSave: (() => void) | undefined
        const saveState = vi.fn(() => new Promise<void>((resolve) => {
            resolveStateSave = resolve
        }))
        const put = vi.fn().mockResolvedValue({
            data: {
                message: 'Die möglichen Stundenpläne wurden berechnet.',
                data: {
                    summary: {
                        timetable_count: 7,
                        timetable_variation_count: 10,
                        conflict_timetable_count: 3,
                    },
                    timetables: [{ id: 'not-rendered-yet' }],
                },
            },
        })
        vi.stubGlobal('axios', { put })
        const context: any = {
            currentStep: 'creation',
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
            allowSaturdayLessons: true,
            stateSaveFailed: false,
            timetableCalculationStatus: 'idle',
            timetableCalculationResult: null,
            timetableCalculationError: '',
            timetableCalculationRequestId: 0,
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
        expect(put).not.toHaveBeenCalled()

        await duplicateCalculation
        resolveStateSave?.()
        await calculation

        expect(put).toHaveBeenCalledOnce()
        expect(put).toHaveBeenCalledWith('/api/admin/students-timetables/timetable-v3/timetable', {
            modules: ['current:D5', 'additional:M5'],
            parameters: {
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
        })
        expect(context.timetableCalculationStatus).toBe('success')
        expect(context.timetableCalculationResult.summary.timetable_count).toBe(7)
    })

    it('shows only the possible variant count and explains the calculation compactly', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const computed = (TimetableV3 as any).computed
        const methods = (TimetableV3 as any).methods
        const countContext = {
            timetableCalculationSummary: {
                timetable_count: 0,
                timetable_variation_count: 12,
                conflict_timetable_count: 12,
            },
            normalizedTimetableCalculationCount: methods.normalizedTimetableCalculationCount,
        }

        expect(computed.possibleTimetableCount.call(countContext)).toBe(0)
        expect(source).toContain('Berechnung der Stundenpläne')
        expect(source).toContain('Mögliche Stundenplanvarianten werden berechnet …')
        expect(source).toContain('aria-busy="true"')
        expect(source).toMatch(/class="timetable-v3__calculation-result"\s+aria-live="polite"\s+role="status"/)
        expect(source).toContain("timetableCalculationSummary.timetable_count")
        expect(source).toContain('Keine möglichen Varianten gefunden')
        expect(source).toContain('Die ausgewählten Unterrichtsalternativen wurden miteinander kombiniert')
        expect(source).toContain('zeitliche Überschneidungen geprüft')
        expect(source).toContain('Varianten mit Konflikten wurden ausgeschlossen')
        expect(source).toContain('timetableCalculationSummary.timetable_variation_count')
        expect(source).toContain('timetableCalculationSummary.conflict_timetable_count')
        expect(source).toContain('TimetableV3PossibleTimetables')
        expect(source).toContain(':timetables="timetableCalculationResult?.timetables || []"')
    })

    it('shows the exact modules used by the backend calculation', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue',
            'utf8',
        )
        const computed = (TimetableV3 as any).computed
        const modules = computed.timetableCalculationModules.call({
            timetableCalculationResult: {
                modules: [
                    {
                        selection_key: 'current:D5',
                        code: 'D5',
                        name: 'Deutsch 5',
                    },
                    {
                        selection_key: 'current:CH1',
                        code: 'CH1',
                        name: 'Chemie 1',
                    },
                ],
            },
            selectedModules: [
                {
                    selection_key: 'current:BU2',
                    code: 'BU2',
                    name: 'Biologie 2',
                },
            ],
        })

        expect(modules).toEqual([
            { key: 'current:D5', code: 'D5', name: 'Deutsch 5' },
            { key: 'current:CH1', code: 'CH1', name: 'Chemie 1' },
        ])
        expect(source).toContain('Verwendete Module')
        expect(source).toContain('Für die Berechnung verwendete Module')
        expect(source).toContain('v-for="module in timetableCalculationModules"')
        expect(source).toContain('{{ module.code }}')
        expect(source).toContain('{{ module.name }}')
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
            source.indexOf('class="timetable-v3__solution-plan"'),
            source.indexOf('</section>', source.indexOf('class="timetable-v3__solution-plan"')),
        )

        expect(renderedScenarios).toEqual(scenarios)
        expect(renderedScenarios.map((scenario: any) => scenario.removed_module_code)).toEqual(['CH1', 'BU2'])
        expect(hasOnlyZeroScenarios).toBe(true)
        expect(hasUnknownScenario).toBe(false)
        expect(methods.timetableSolutionPlanCountLabel.call({
            normalizedTimetableCalculationCount,
        }, 1200)).toBe((1200).toLocaleString('de-AT'))
        expect(source).toContain("possibleTimetableCount === 0 && timetableSolutionPlanModuleRemovalScenarios.length")
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
        const put = vi.fn(async () => {
            events.push('put')

            return {
                data: {
                    data: { summary: { timetable_count: 7 } },
                },
            }
        })
        vi.stubGlobal('axios', { put })
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
            allowSaturdayLessons: false,
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
        expect(events).toEqual(['reset', 'calculate', 'save', 'put'])
        expect(context.saveState).toHaveBeenCalledOnce()
        expect(context.calculatePossibleTimetables).toHaveBeenCalledOnce()
        expect(put).toHaveBeenCalledOnce()
        expect(put.mock.invocationCallOrder[0]).toBeGreaterThan(context.saveState.mock.invocationCallOrder[0])
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
        const put = vi.fn().mockResolvedValue({
            data: {
                data: {
                    summary: [],
                    timetables: [],
                },
            },
        })
        vi.stubGlobal('axios', { put })
        const context: any = {
            currentStep: 'creation',
            selectedModuleKeys: ['current:D5'],
            selectedCourseKeys: ['d5-a'],
            planningMode: 'without_student',
            selectedStudentCode: '',
            planningSelectionValues: {},
            allowSaturdayLessons: false,
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
        const put = vi.fn().mockRejectedValue({
            response: {
                data: {
                    message: 'Für diese Auswahl fehlen Unterrichtsdaten.',
                },
            },
        })
        vi.stubGlobal('axios', { put })
        const context: any = {
            currentStep: 'creation',
            selectedModuleKeys: ['current:D5'],
            selectedCourseKeys: ['d5-a'],
            planningMode: 'without_student',
            selectedStudentCode: '',
            planningSelectionValues: {},
            allowSaturdayLessons: false,
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
        expect(context.allowSaturdayLessons).toBe(false)
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
        let resolveCalculation: ((value: unknown) => void) | undefined
        const put = vi.fn(() => new Promise((resolve) => {
            resolveCalculation = resolve
        }))
        vi.stubGlobal('axios', { put })
        const context: any = {
            currentStep: 'creation',
            selectedModuleKeys: ['current:D5'],
            selectedCourseKeys: ['d5-a'],
            planningMode: 'without_student',
            selectedStudentCode: '',
            planningSelectionValues: {},
            allowSaturdayLessons: false,
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
        await vi.waitFor(() => expect(put).toHaveBeenCalledOnce())

        methods.resetTimetableCalculation.call(context)
        resolveCalculation?.({
            data: {
                data: {
                    summary: { timetable_count: 99 },
                },
            },
        })
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
                creationOptions: { allowSaturdayLessons: true },
            },
            planningMode: null,
            selectedStudent: null,
            allowSaturdayLessons: false,
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

        expect(context.allowSaturdayLessons).toBe(true)
        expect(context.planningMode).toBe('with_student')
        expect(context.selectedStudent).toEqual({ studentCode: '1001' })
        expect(callOrder).toEqual(['modules', 'student', 'mode'])
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
            context: { planning_mode: 'with_student', student_code: '1001' },
            modules: [{
                selection_key: 'current:CH1',
                selected_course_keys: ['ch1-a', 'ch1-b'],
            }],
            parameters: {
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
            timetables: [],
        }
        const get = vi.fn().mockResolvedValue({ data: { data: persistedResult } })
        vi.stubGlobal('axios', { get })
        const context: any = {
            currentStep: 'creation',
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
            allowSaturdayLessons: true,
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
            '/api/admin/students-timetables/timetable-v3/timetable?planning_mode=with_student&student_code=1001',
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
        context.allowSaturdayLessons = false
        await methods.restorePersistedTimetableCalculation.call(context)
        expect(context.timetableCalculationStatus).toBe('idle')
        expect(context.timetableCalculationResult).toBeNull()

        context.allowSaturdayLessons = true
        persistedResult.parameters.selection.semester = 4
        await methods.restorePersistedTimetableCalculation.call(context)
        expect(context.timetableCalculationStatus).toBe('idle')
        expect(context.timetableCalculationResult).toBeNull()

        context.currentStep = 'modules'
        await methods.restorePersistedTimetableCalculation.call(context)
        expect(get).toHaveBeenCalledTimes(6)
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
            allowSaturdayLessons: false,
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
            '/api/admin/students-timetables/timetable-v3/timetable?planning_mode=without_student',
        )
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
            restoreCreationOptions: vi.fn(),
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
        expect(source).toContain("usesMainModuleGroups ? 'Alle Module auswählen'")
        expect(source).toContain("usesMainModuleGroups ? 'Alle Module abwählen'")
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
            scheduleCreationMode: 'automatic',
            allowSaturdayLessons: true,
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
                    scheduleCreationMode: 'automatic',
                    selectedKeys: ['current:D5'],
                    selectedCourseKeys: ['d5-a'],
                },
                creationOptions: {
                    allowSaturdayLessons: true,
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
            planningMode: 'without_student',
            selectedStudent: null,
            selectedStudentCode: '',
            planningSelectionValues: {},
            selectedModuleKeys: [],
            selectedCourseKeys: [],
            scheduleCreationMode: 'automatic',
            allowSaturdayLessons: false,
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
