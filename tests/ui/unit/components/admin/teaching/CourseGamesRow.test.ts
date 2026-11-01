import { afterEach, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import axios from 'axios'
import CourseGamesRow from '@/pages/admin/teaching/overview/components/CourseGamesRow.vue'

vi.mock('axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@/pages/admin/teachingTools/NumberSystemsGame.vue', () => ({ __esModule: true, default: {
    props: ['sessionId'], template: '<div data-test="game-result">Ergebnis {{ sessionId }}</div>',
} }))
const wrappers = []
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); vi.resetAllMocks() })
const dialog = defineComponent({ props: ['modelValue'], setup(props, { slots }) { return () => props.modelValue ? h('div', { role: 'dialog' }, slots.default?.()) : null } })
function render(courseId = 18) {
    const wrapper = mount(CourseGamesRow, { props: { course: { id: courseId, title: 'DGB 3B' }, dates: [
        { id: 1, date: '2026-07-02' }, { id: 2, date: '2026-07-02' }, { id: 3, date: '2026-07-03' },
    ] }, global: { stubs: { 'v-dialog': dialog, 'v-btn': { template: '<button><slot /></button>' } } } })
    wrappers.push(wrapper)
    return wrapper
}

it('places course rounds on their Vienna start date once and opens the existing result', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: { sessions: [
        { id: 4, title: 'Abendrunde', teaching_course_id: 18, started_at: '2026-07-01T22:30:00Z', status: 'ended' },
        { id: 5, title: 'Zweite Runde', teaching_course_id: 18, started_at: '2026-07-02T10:30:00Z', status: 'ended' },
        { id: 6, title: 'Fremder Kurs', teaching_course_id: 99, started_at: '2026-07-02T10:30:00Z' },
    ] } })
    const wrapper = render(); await flushPromises()
    expect(axios.get).toHaveBeenCalledExactlyOnceWith('/api/admin/number-systems?course_id=18', { signal: expect.any(AbortSignal) })
    const cells = wrapper.findAll('td')
    expect(cells[0].text()).toContain('Abendrunde').toContain('Zweite Runde').toContain('02.07.2026 um 00:30 Uhr')
    expect(cells[1].text()).toBe('')
    expect(cells[2].text()).toBe('')
    expect(wrapper.text()).not.toContain('Fremder Kurs')
    await wrapper.get('[aria-label="Ergebnisse von Abendrunde ansehen"]').trigger('click'); await flushPromises()
    expect(wrapper.get('[data-test="game-result"]').text()).toBe('Ergebnis 4')
})

it('keeps rounds outside course dates and unstarted rounds accessible without inventing play dates', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: { sessions: [
        { id: 4, title: 'Ferienrunde', teaching_course_id: 18, started_at: '2026-08-01T08:00:00Z', status: 'ended' },
        { id: 5, title: 'Vorbereitung', teaching_course_id: 18, created_at: '2026-07-01T08:00:00Z', status: 'waiting' },
    ] } })
    const wrapper = render(); await flushPromises()
    expect(wrapper.findAll('td').every(cell => cell.text() === '')).toBe(true)
    await wrapper.get('th > button').trigger('click')
    const list = wrapper.get('[role="dialog"]')
    expect(list.text()).toContain('Ferienrunde').toContain('01.08.2026 um 10:00 Uhr')
    expect(list.text()).toContain('Vorbereitung').toContain('Noch nicht gestartet').toContain('Erstellt: 01.07.2026 um 10:00 Uhr')
    expect(list.findAll('li')[0].text()).toContain('Ferienrunde')
})

it('discards a late response after switching courses and provides retry after a failed load', async () => {
    let resolveFirst
    vi.mocked(axios.get).mockImplementationOnce(() => new Promise(resolve => { resolveFirst = resolve }))
        .mockRejectedValueOnce(new Error('offline')).mockResolvedValue({ data: { sessions: [] } })
    const wrapper = render()
    const signal = vi.mocked(axios.get).mock.calls[0][1].signal
    await wrapper.setProps({ course: { id: 19, title: 'Neuer Kurs' } }); await flushPromises()
    expect(signal.aborted).toBe(true)
    resolveFirst({ data: { sessions: [{ id: 4, title: 'Alte Runde', teaching_course_id: 18 }] } }); await flushPromises()
    expect(wrapper.text()).toContain('Spiele erneut laden')
    expect(wrapper.text()).not.toContain('Alte Runde')
    await wrapper.get('th > button').trigger('click'); await flushPromises()
    expect(wrapper.text()).toContain('Alle Runden (0)')
})
