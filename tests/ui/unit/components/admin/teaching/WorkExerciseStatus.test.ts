import { expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import WorkExerciseStatus from '@/pages/admin/teaching/overview/components/WorkExerciseStatus.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'
import { workDropboxStates } from '@/helpers/workDropbox'

const work = { id: 65, teaching_course_id: 2 }
it('opens creation from the missing LF icon with mouse and keyboard without claiming an existing folder', async () => {
    const wrapper = render('missing', { canCreate: true })
    try {
        const icon = wrapper.get('[role="button"]')
        await icon.trigger('click')
        await icon.trigger('keydown', { key: 'Enter' })
        await icon.trigger('keydown', { key: ' ' })
        expect(wrapper.emitted('create')).toHaveLength(3)
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        await wrapper.setProps({ disabled: true })
        await wrapper.get('[aria-disabled="true"]').trigger('click')
        expect(wrapper.emitted('create')).toHaveLength(3)
    } finally { wrapper.unmount() }
})

it('does not offer creation from an unchecked or foreign LF state', async () => {
    const wrapper = render('missing', { canCreate: true, state: { userId: 99, status: 'missing' } })
    try {
        expect(wrapper.find('[role="button"]').exists()).toBe(false)
        expect(wrapper.emitted('create')).toBeUndefined()
    } finally { wrapper.unmount() }
})
const state = { userId: 7, workId: 65, courseId: 2, date: '2026-10-20', assignmentStatus: 'ready', folder: { id: 'id:exercise', name: 'Bestätigter Ordner', path: '/Materialien/Prüfung' } }
function render(status = 'checking', props = {}) {
    return mount(WorkExerciseStatus, { props: { work, date: state.date, state: { ...state, status }, ...props }, global: {
        plugins: [createTestingPinia({ createSpy: vi.fn, initialState: { AdminAdminStore: { config: { user: { id: 7 } } } } })],
        stubs: {
            'v-dialog': { name: 'VDialog', props: { persistent: Boolean }, template: '<section role="dialog"><slot /></section>' },
            'v-btn': { template: '<button><slot /></button>' },
            WorkExercisePdf: { name: 'WorkExercisePdf', props: { work: Object, date: String, section: String },
                template: '<button>{{ section }} (PDF)</button>' },
        },
    } })
}

it('blocks the exercise dialog while folder-dependent actions are disabled', async () => {
    const wrapper = render('found', { disabled: true })
    try {
        expect(wrapper.get('[aria-disabled="true"]').attributes('role')).toBeUndefined()
        await wrapper.get('[aria-disabled="true"]').trigger('click')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        await wrapper.setProps({ disabled: false })
        await wrapper.get('[role="button"]').trigger('click')
        expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
    } finally { wrapper.unmount() }
})

it('does not claim an assignment exists when only its folder is confirmed', async () => {
    const wrapper = render('found', { state: { ...state, status: 'found', assignmentStatus: 'missing' } })
    try {
        expect(wrapper.get('v-icon').attributes('color')).toBe('warning')
        await wrapper.get('[role="button"]').trigger('click')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Aufgabenstellung ist noch nicht bestätigt')
    } finally { wrapper.unmount() }
})

it('does not show a missing exercise warning for an existing imported work', () => {
    const wrapper = render('missing', { work: { ...work, status: { folder_imported_at: '2026-10-13T12:54:00Z' } } })
    expect(wrapper.find('[role="img"]').exists()).toBe(false)
    wrapper.unmount()
})

it('does not show a missing exercise warning when only the server confirms an older import', () => {
    workDropboxStates.set('7:2:65', { has_import: true })
    const wrapper = render('missing')
    try {
        expect(wrapper.find('[role="img"]').exists()).toBe(false)
    } finally { wrapper.unmount(); workDropboxStates.delete('7:2:65') }
})

it('keeps the existing assignment accessible after importing results and linking its historical folder', async () => {
    workDropboxStates.set('7:2:65', { has_import: true, folder: { id: 'id:old', name: 'Originalordner' } })
    const wrapper = render('missing', { work: { ...work, title: 'Dateimanagement', status: {
        assessment_json_packages: [{ exercise_id: 'old' }], assessment_json_imports: [{ student_id: 101 }],
    } } })
    try {
        expect(wrapper.get('[role="img"]').attributes('color')).toBe('success')
        await wrapper.get('[role="button"]').trigger('click')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Bestehende Leistungsfeststellung')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Originalordner')
        expect(wrapper.get('[role="dialog"]').text()).not.toContain('Prüfungsordner bestätigt')
        expect(wrapper.findAllComponents({ name: 'WorkExercisePdf' })).toHaveLength(2)
        expect(wrapper.findAll('button').some(button => button.text() === 'Leistungsfeststellungsordner öffnen')).toBe(false)
    } finally { wrapper.unmount(); workDropboxStates.delete('7:2:65') }
})

it('uses the confirmed exercise folder for a work with existing results', async () => {
    const wrapper = render('found', { work: { ...work, status: {
        assessment_json_packages: [{ exercise_id: 'existing' }], assessment_json_imports: [{ student_id: 101 }],
    } } })
    try {
        await wrapper.get('[role="button"]').trigger('click')
        expect(wrapper.get('[role="dialog"]').text()).toContain('/Materialien/Prüfung')
        expect(wrapper.get('[role="dialog"]').text()).toContain('Angaben (PDF)')
    } finally { wrapper.unmount() }
})

it.each([['found', 'success', 'erstellt: Bestätigter Ordner'], ['missing', 'error', 'noch nicht erstellt'],
    ['checking', 'grey', 'wird geprüft'], ['unknown', 'grey', 'konnte nicht geprüft']])('distinguishes %s without another lookup', (status, color, label) => {
    const wrapper = render(status)
    expect(wrapper.get('v-icon').attributes('color')).toBe(color)
    expect(wrapper.get('v-icon').attributes('aria-label')).toContain(label)
    expect(wrapper.text()).toBe('mdi-clipboard-text-outline')
    expect(wrapper.find('button').exists()).toBe(false)
    wrapper.unmount()
})

const course = {
    id: 2, title: 'DGB · Gruppe 1', classes: ['3B'],
    teaching_entry_area: { entry_definitions: [{ category: 'Benotung', short_name: 'A5', name: 'Auftrag', maximum_points: 5 }] },
    students_info: [
        { id: 1, user_id: 101 }, { id: 2, user_id: 102 }, { id: 3, user_id: 103, canceled_at: '2026-10-01' },
        { id: 4, user_id: 104, deleted_at: '2026-10-01' }, { id: 5, user_id: 105 },
    ],
}
const savedWork = {
    ...work, title: 'E-Mails', type: 'A5', is_group_work: false,
    date_for_all_groups: '2026-10-19', finish_until_date: '2026-10-23', finish_until_time: null,
    description: 'PDF erstellen und per E-Mail abgeben.',
    groups: [{ name: 'Gruppe 1', date: state.date, student_ids: [101, 102, 103, 104] },
        { name: 'Gruppe 2', date: '2026-10-21', student_ids: [105] }],
}

function info(wrapper: ReturnType<typeof render>) {
    const terms = wrapper.findAll('dt')
    const values = wrapper.findAll('dd')
    return Object.fromEntries(terms.map((term, index) => [term.text(), values[index].text()]))
}

it('opens the completed LF as persistent read-only information for the selected date', async () => {
    const wrapper = render('found', { work: savedWork, course })
    await wrapper.get('[role="button"]').trigger('click')

    expect(wrapper.getComponent({ name: 'VDialog' }).props('persistent')).toBe(true)
    expect(info(wrapper)).toMatchObject({ Titel: 'E-Mails', Typ: 'A5 · Auftrag', Kurs: 'DGB · Gruppe 1', Klasse: '3B',
        Durchführung: '20.10.2026', Arbeitsform: 'Einzelarbeit',
        'Teilnehmer:innen aktuell': '2', 'Maximale Punkte': '5', Hauptfrist: '23.10.2026', 'Mitgeteilte Frist': 'Nicht mitgeteilt' })
    expect(wrapper.text()).toContain('/Materialien/Prüfung')
    expect(wrapper.text()).toContain(savedWork.description)
    expect(info(wrapper).Prüfungsgruppe).toBeUndefined()
    expect(wrapper.find('input, textarea, [contenteditable="true"]').exists()).toBe(false)
    expect(wrapper.findAllComponents({ name: 'WorkExercisePdf' }).map(component => component.props('section')))
        .toEqual(['Angaben', 'Bewertungskriterien'])
    expect(wrapper.text()).toContain('Angaben (PDF)')
    expect(wrapper.text()).toContain('Bewertungskriterien (PDF)')
    expect(wrapper.text()).not.toContain('Ordner und Dateien anzeigen')
    await wrapper.findAll('button').find(button => button.text() === 'Schließen')!.trigger('click')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    wrapper.unmount()
})

it('shows the MC tests for the selected date and all groups, without mixing in other dates', async () => {
    const wrapper = render('found', { course, work: { ...savedWork, multiple_choice_test_titles: {
        11: 'E-Mails Gruppe 1', 12: 'Gemeinsamer Test', 13: 'E-Mails Gruppe 2',
    }, status: { multiple_choice_tests: {
        '2026-10-20': { work_id: 65, test_id: 11 }, all: { work_id: 65, test_id: 12 },
        '2026-10-21': { work_id: 65, test_id: 13 },
    } } } })
    try {
        await wrapper.get('[role="button"]').trigger('click')
        expect(info(wrapper)['MC-Tests']).toBe('E-Mails Gruppe 1, Gemeinsamer Test')
        expect(wrapper.get('[role="dialog"]').text()).not.toContain('E-Mails Gruppe 2')
    } finally { wrapper.unmount() }
})

it('shows the same test once and does not invent a title for an unverified work binding', async () => {
    const wrapper = render('found', { course, work: { ...savedWork, status: { multiple_choice_tests: {
        '2026-10-20': { work_id: 65, test_id: 11 }, all: { work_id: 65, test_id: 11 },
    } } } })
    try {
        await wrapper.get('[role="button"]').trigger('click')
        expect(info(wrapper)['MC-Test']).toBe('Nr. 11')
        await wrapper.setProps({ work: { ...savedWork, status: { multiple_choice_tests: { '2026-10-20': { work_id: 66, test_id: 11 } } } } })
        expect(info(wrapper)['MC-Test']).toBeUndefined()
    } finally { wrapper.unmount() }
})

it('also shows a linked MC test in the historical exercise dialog', async () => {
    workDropboxStates.set('7:2:65', { has_import: true })
    const wrapper = render('missing', { work: { ...savedWork, multiple_choice_test_titles: { 11: 'MC E-Mails' }, status: {
        assessment_json_packages: [{ exercise_id: 'old' }], assessment_json_imports: [{ student_id: 101 }],
        multiple_choice_tests: { '2026-10-20': { work_id: 65, test_id: 11 } },
    } } })
    try {
        await wrapper.get('[role="button"]').trigger('click')
        expect(info(wrapper)['MC-Test']).toBe('MC E-Mails')
    } finally { wrapper.unmount(); workDropboxStates.delete('7:2:65') }
})

it.each(['missing', 'checking', 'unknown'])('does not open an information dialog for %s LF status', async status => {
    const wrapper = render(status)
    await wrapper.get('span').trigger('click')
    expect(wrapper.find('[role="button"]').exists()).toBe(false)
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    wrapper.unmount()
})

it.each(['enter', 'space'])('opens with the %s key and closes when the confirmed context changes', async key => {
    const wrapper = render('found')
    await wrapper.get('[role="button"]').trigger(`keydown.${key}`)
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
    await wrapper.setProps({ date: '2026-10-21' })
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    wrapper.unmount()
})

it('shows saved deadline times and uses active course members when no groups exist', async () => {
    const wrapper = render('found', { course, work: { ...savedWork, groups: [], finish_until_time: '00:00:00',
        communicated_deadline: { date: '2026-10-22', time: '15:45:00' } } })
    await wrapper.get('[role="button"]').trigger('click')
    expect(info(wrapper)).toMatchObject({ 'Teilnehmer:innen aktuell': '3', Hauptfrist: '23.10.2026, 00:00 Uhr',
        'Mitgeteilte Frist': '22.10.2026, 15:45 Uhr' })
    wrapper.unmount()
})

it('never mixes another course or missing date assignment into the LF information', async () => {
    const wrapper = render('found', { course: { ...course, id: 3 }, work: savedWork })
    await wrapper.get('[role="button"]').trigger('click')
    expect(info(wrapper)).toMatchObject({ Kurs: 'Nicht angegeben', 'Teilnehmer:innen aktuell': 'Nicht bestätigt' })
    expect(info(wrapper)['Maximale Punkte']).toBeUndefined()
    await wrapper.setProps({ course, work: { ...savedWork, groups: [{ date: '2026-10-21', student_ids: [105] }] } })
    await wrapper.get('[role="button"]').trigger('click')
    expect(info(wrapper)['Teilnehmer:innen aktuell']).toBe('Nicht bestätigt')
    wrapper.unmount()
})

it('keeps previous work, date and account results neutral', async () => {
    const wrapper = render('found')
    await wrapper.setProps({ work: { ...work, id: 66 } })
    expect(wrapper.get('v-icon').attributes('color')).toBe('grey')
    await wrapper.setProps({ work, date: '2026-10-21' })
    expect(wrapper.get('v-icon').attributes('color')).toBe('grey')
    await wrapper.setProps({ date: state.date })
    expect(wrapper.get('v-icon').attributes('color')).toBe('success')
    useAdminStore().config.user.id = 8
    await wrapper.vm.$nextTick()
    expect(wrapper.get('v-icon').attributes('color')).toBe('grey')
    wrapper.unmount()
})
