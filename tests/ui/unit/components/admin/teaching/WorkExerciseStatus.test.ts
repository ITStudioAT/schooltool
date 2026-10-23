import { expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import WorkExerciseStatus from '@/pages/admin/teaching/overview/components/WorkExerciseStatus.vue'
import { useAdminStore } from '@/stores/admin/AdminStore'

const work = { id: 65, teaching_course_id: 2 }
const state = { userId: 7, workId: 65, courseId: 2, date: '2026-10-20', folder: { id: 'id:exercise', name: 'Bestätigter Ordner', path: '/Materialien/Prüfung' } }
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
