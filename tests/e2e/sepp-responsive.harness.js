import { createApp, h, ref } from 'vue'
import { createPinia } from 'pinia'
import { createVuetify } from 'vuetify'
import { VApp, VMain } from 'vuetify/components'
import 'vuetify/styles'
import { useStudentTimetablesUserStore } from '@/stores/studentsTimetables/StudentTimetablesUserStore'
import { useAdminStore } from '@/stores/admin/AdminStore'

const parameters = new URLSearchParams(location.search)
const scenario = parameters.get('scenario') || 'student-overview'
if (scenario.startsWith('admin-')) {
    await import('../../resources/css/admin.css')
} else {
    await import('../../resources/css/homepage.css')
}
const dialog = parameters.get('dialog') || ''
const pinia = createPinia()
const student = {
    id: 1,
    code: 'fixture-student',
    student_code: 'fixture-student',
    canOpenStudentView: true,
    first_name: 'Alexandermaria',
    last_name: 'MustermannmitbesonderslangemDoppelnamen',
    email: 'alexandermaria.mustermannmitlangemnamen@example.test',
    class: '8AK',
    schoolclass: '8AK',
    religion: 'Römisch-katholisch',
    sex: 'w',
}
const courses = Array.from({ length: 8 }, (_, index) => ({
    key: `course-${index}`,
    selection_key: `course-${index}`,
    selection_keys: [`course-${index}`],
    code: `D${index + 1}`,
    course_title: 'Deutsche Sprache und Kommunikation mit wissenschaftlichen Arbeitstechniken',
    title: `D${index + 1} – Unterricht am Abend`,
    hours_label: '4 Wochenstunden',
    instruction_label: 'Präsenzunterricht und Fernunterricht',
    dates_count: 18,
    schedule_rows: [{ key: 'monday', label: 'Montag 17:50–21:05 · jede zweite Woche' }],
}))
const module = {
    code: 'D1', name: 'Deutsche Sprache und Kommunikation mit wissenschaftlichen Arbeitstechniken',
    selection_key: 'module-d1', hours: 4, courses,
}
const moduleGroups = [{ key: 'german', label: 'Sprachen und Kommunikation', modules: [module] }]
const selectionFields = [{
    key: 'instruction_type', label: 'Studienform', selected_value: 'evening',
    options: [{ value: 'evening', title: 'Abendunterricht mit Fernstudienanteilen' }],
}]
const studentStore = useStudentTimetablesUserStore(pinia)
studentStore.$patch({
    user: student,
    school: { id: 1, long_name: 'Bundesgymnasium für Berufstätige mit ausführlicher Schulbezeichnung', short_name: 'BG Wien' },
    config: { health: { queue_working: true } },
    overview: {
        student,
        selection: { instruction_type: 'evening' },
        student_information: {
            instruction_type: 'Abendunterricht mit Fernstudienanteilen', subject_plan: 'Allgemeinbildende höhere Schule',
            module_selection_groups: moduleGroups, main_module_selection_groups: moduleGroups,
            selection_fields: selectionFields, module_groups: [],
        },
    },
})
useAdminStore(pinia).config = { roles: ['admin'], selected_schoolyear: { name: '2026/2027', concerns: '2026/2027' } }

const route = { path: '/students-timetables/overview', params: {}, query: {} }
let component
const state = { pageLoading: false, studentTimetablesStore: studentStore }
let props = {}

if (scenario === 'admin-subjects') {
    component = (await import('@/pages/admin/studentsTimetables/subjectsOverview/SubjectsOverview.vue')).default
    route.params = { subsection: 'subject-plan-v2' }
    Object.assign(state, { subject_action: 'subject-plan-v2', studyProgram: 'normalstudium', subjectRows: [], settingsLoading: false })
} else if (scenario === 'admin-tests-dialog') {
    component = (await import('@/pages/admin/studentsTimetables/testsV3/TestsV3.vue')).default
    route.params = { subsection: 'students' }
    Object.assign(state, { studentV3TestSummaryDialog: true, testReadiness: { ready: true }, students: [] })
} else if (scenario === 'admin-import-dialog') {
    component = (await import('@/pages/admin/studentsTimetables/timetable/Timetable.vue')).default
    route.params = { subsection: 'imports', detail: 'stundenplan', action: 'import' }
    Object.assign(state, {
        subAction: 'imports', importPage: 'stundenplan', importSubPage: 'import',
        recognitionDeleteDialog: true,
        recognitionDeleteTargetImport: { original_filename: 'AnerkennungsmodulemitbesonderslangemDateinamen2026.csv' },
    })
} else if (scenario.startsWith('admin-') && scenario !== 'admin-appbar') {
    component = (await import('@/pages/admin/studentsTimetables/timetableV3/TimetableV3.vue')).default
    route.path = '/admin/students-timetables/timetable-v3/overview'
    route.params = { section: 'timetable-v3', subsection: scenario.replace('admin-', '') }
    Object.assign(state, {
        isLoadingState: false, planningMode: 'with_student', selectedStudent: student,
        moduleSelectionGroups: moduleGroups, mainModuleSelectionGroups: moduleGroups,
        planningSelectionFields: selectionFields, schoolHoursLoaded: true,
        scheduleCreationMode: 'automatic', activeModuleGroupKey: 'german',
    })
} else if (['student-create', 'student-results', 'student-adoption'].includes(scenario)) {
    component = (await import('@/pages/homepage/studentsTimetables/overviewV2/OverviewV2.vue')).default
    route.path = scenario === 'student-create' ? '/students-timetables/create'
        : scenario === 'student-results' ? '/students-timetables/create/results' : '/students-timetables/create/adoption'
    Object.assign(state, { scheduleCreationMode: scenario === 'student-create' ? 'automatic' : 'manual', activeModuleGroupKey: 'german' })
} else if (scenario === 'student-login') {
    component = (await import('@/pages/homepage/studentsTimetables/StudentTimetables.vue')).default
    state.login_step = 'code_sent'
} else if (scenario === 'student-profile') {
    component = (await import('@/pages/homepage/studentsTimetables/profile/Profile.vue')).default
} else if (scenario === 'student-legacy') {
    component = (await import('@/pages/homepage/studentsTimetables/overview/Overview.vue')).default
    Object.assign(state, { showManualTimetable: true, manualTimetableMode: 'personal' })
    studentStore.overview.personal_timetable = {
        id: 1,
        timetable: {
            weekdays: ['Mo', 'Di', 'Mi', 'Do', 'Fr'].map(label => ({ label })),
            semesters: [{ label: 'Wintersemester', weeks: [{ label: '14.–18. September', hours: [{
                hour: 1, from: '17:50', until: '18:35',
                cells: [{ courses: [{ label: 'D1', details: 'Deutsche Sprache und Kommunikation' }] }, {}, {}, {}, {}],
            }] }] }],
        },
    }
} else if (scenario === 'admin-appbar') {
    component = (await import('@/pages/admin/components/AdminAppBar.vue')).default
    props = {
        modelValue: false, isVisible: true, canManageSchoolwideSchoolyear: true, title: 'SEPP',
        selectedSchoolLogoSrc: '/wide-logo.svg',
        selectedSchoolyear: parameters.has('missing') ? null : { name: '2026/2027' },
        schoolwideActiveSchoolyear: parameters.has('missing') ? null : { name: '2026/2027' },
    }
} else if (scenario === 'shared-timetable') {
    component = (await import('@/pages/admin/studentsTimetables/timetableV3/TimetableV3PossibleTimetables.vue')).default
    props = {
        selectedIndex: 0, totalCount: 2, allowSaturdayLessons: true,
        timetables: [1, 2].map(number => ({
            key: `timetable-${number}`, number, type: 'full_green',
            slots: { '1-1': {
                key: 'lesson', code: 'D1', name: module.name, sourceLabel: 'D1 – Abendunterricht',
                courseGroup: { weekday: 1, hour: 1, starts_at: '17:50', ends_at: '18:35', dates: ['2026-09-14'] },
                conflicts: [], sameSlotEntries: [],
            } },
        })),
    }
} else {
    component = (await import('@/pages/homepage/studentsTimetables/overviewV2/OverviewV2.vue')).default
}

if (['student-results', 'admin-creation'].includes(scenario)) {
    Object.assign(state, {
        timetableCalculationStatus: 'success', selectedModuleKeys: ['module-d1'], selectedCourseKeys: ['course-0'],
        timetableCalculationResult: {
            summary: { possible_timetable_count: 2, checked_combinations: 128, total_combinations: 128 },
            timetables: [1, 2].map(number => ({
                key: `result-${number}`, number, type: 'full_green', metrics: { free_days: 3, gap_count: 0 },
                slots: { '1-1': {
                    key: 'course-0', code: 'D1', name: module.name, sourceLabel: 'D1 – Abendunterricht',
                    courseGroup: { weekday: 1, hour: 1, starts_at: '17:50', ends_at: '18:35', dates: ['2026-09-14'] },
                    conflicts: [], sameSlotEntries: [],
                } },
            })),
        },
    })
}

if (dialog === 'module') Object.assign(state, { moduleCoursesDialogOpen: true, moduleCourseDialogModule: module })
if (dialog === 'info') state.studentInfoDialogOpen = true
if (dialog === 'study') state.studyInfoDialogOpen = true
if (dialog === 'drawer') state.showDrawer = true

// Preserve real templates, CSS, computed properties and interactions. Only replace
// network initialization and persistence; fixtures must never access live records.
const fixture = {
    ...component,
    created: undefined, beforeMount: undefined, mounted: undefined, watch: {}, beforeRouteUpdate: undefined,
    data() { return { ...component.data?.call(this), ...state } },
}
const selectedIndex = ref(0)
const app = createApp({
    render() {
        return h(VApp, {}, { default: () => h(VMain, {}, {
            default: () => h(fixture, {
                ...props,
                ...(scenario === 'shared-timetable' ? { selectedIndex: selectedIndex.value, onNavigate: index => selectedIndex.value = index } : {}),
            }),
        }) })
    },
})
app.config.globalProperties.$route = route
app.config.globalProperties.$router = { push() {}, replace() {} }
app.use(pinia).use(createVuetify()).mount('#app')
document.documentElement.dataset.fixtureReady = 'true'
