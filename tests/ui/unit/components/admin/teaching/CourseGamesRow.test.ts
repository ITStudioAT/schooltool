import { afterEach, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import axios from 'axios'
import CourseGamesRow from '@/pages/admin/teaching/overview/components/CourseGamesRow.vue'

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }))
vi.mock('@/pages/admin/teachingTools/NumberSystemsGame.vue', () => ({ __esModule: true, default: {
    props: ['sessionId', 'courseId', 'courseDateId'], template: '<div data-test="game-result">Ergebnis {{ sessionId }}<span v-if="courseId">Kurs {{ courseId }} Termin {{ courseDateId }}</span></div>',
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
    expect(cells[0].text()).toContain('Abendrunde').toContain('Zweite Runde').toContain('02.07. 00:30')
    expect(cells[1].findAll('.course-game-card')).toHaveLength(0)
    expect(cells[2].findAll('.course-game-card')).toHaveLength(0)
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
    expect(wrapper.findAll('td .course-game-card')).toHaveLength(0)
    await wrapper.get('th > button').trigger('click')
    const list = wrapper.get('[role="dialog"]')
    expect(list.text()).toContain('Ferienrunde').toContain('01.08. 10:00')
    expect(list.text()).toContain('Vorbereitung').toContain('Vorbereitet: 01.07. 10:00')
    expect(list.findAll('li')[0].text()).toContain('Ferienrunde')
})

it('replaces the game card with the named adventure and keeps the game accessible from plus', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: { sessions: [] } })
    vi.mocked(axios.post).mockResolvedValue({ data: { id: 2, games: ['networks', 'number-systems'] } })
    const wrapper = render(); await flushPromises()
    await wrapper.findAll('.course-game-add')[1].trigger('click'); await vi.dynamicImportSettled(); await flushPromises()
    expect(wrapper.get('[role="dialog"]').text()).toContain('DGB 3B').toContain('02.07.2026')
    await wrapper.get('[aria-label="Zahlensysteme hinzufügen"]').trigger('click'); await flushPromises()
    expect(axios.post).toHaveBeenCalledExactlyOnceWith('/api/admin/teaching/course_dates/2/games', { game: 'number-systems' })
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    const cells = wrapper.findAll('td')
    expect(cells[0].findAll('.course-game-card')).toHaveLength(0)
    expect(cells[1].text()).toContain('Netzwerke').toContain('Zahlensysteme')
    await wrapper.get('[aria-label="Zahlensysteme für 02.07.2026 öffnen"]').trigger('click'); await flushPromises()
    expect(wrapper.get('[data-test="game-result"]').text()).toContain('Kurs 18 Termin 2')

    vi.mocked(axios.get).mockResolvedValue({ data: { sessions: [
        { id: 8, title: 'Signal Null xxx', teaching_course_id: 18, teaching_course_date_id: 2, status: 'waiting', created_at: '2026-07-01T20:44:00Z' },
    ] } })
    await wrapper.findAll('[role="dialog"] button').find(button => button.text() === 'Schließen').trigger('click')
    await flushPromises()

    expect(cells[1].findAll('.course-game-card')).toHaveLength(2)
    expect(cells[1].text()).toContain('Netzwerke').toContain('Vorbereitet: 01.07. 22:44')
    expect(cells[1].find('[aria-label="Zahlensysteme für 02.07.2026 öffnen"]').exists()).toBe(false)
    await cells[1].get('[aria-label="Ergebnisse von Signal Null xxx ansehen"]').trigger('click')
    await flushPromises()
    expect(wrapper.get('[data-test="game-result"]').text()).toBe('Ergebnis 8')

    await wrapper.findAll('[role="dialog"] button').find(button => button.text() === 'Schließen').trigger('click')
    await flushPromises()
    await cells[1].get('.course-game-add').trigger('click')
    await wrapper.get('[role="dialog"] [aria-label="Zahlensysteme öffnen"]').trigger('click')
    await flushPromises()
    expect(wrapper.get('[data-test="game-result"]').text()).toContain('Kurs 18 Termin 2')
    expect(axios.post).toHaveBeenCalledTimes(1)
})

it('keeps a failed selection open for retry and disables duplicate assignments', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: { sessions: [] } })
    vi.mocked(axios.post).mockRejectedValueOnce(new Error('offline')).mockResolvedValue({ data: { id: 1, games: ['networks'] } })
    const wrapper = render(); await flushPromises()
    await wrapper.findAll('.course-game-add')[0].trigger('click'); await vi.dynamicImportSettled(); await flushPromises()
    await wrapper.get('[aria-label="Netzwerke hinzufügen"]').trigger('click'); await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain('konnte nicht hinzugefügt werden')
    expect(wrapper.findAll('td .course-game-card')).toHaveLength(0)
    await wrapper.get('[aria-label="Netzwerke hinzufügen"]').trigger('click'); await flushPromises()
    await wrapper.findAll('.course-game-add')[0].trigger('click'); await flushPromises()
    expect(wrapper.get('[aria-label="Netzwerke hinzufügen"]').attributes('disabled')).toBeDefined()
    expect(wrapper.get('[aria-label="Zahlensysteme hinzufügen"]').attributes('disabled')).toBeUndefined()
    await wrapper.findAll('[role="dialog"] button').find(button => button.text() === 'Abbrechen').trigger('click')
    expect(axios.post).toHaveBeenCalledTimes(2)
})

it('restores saved assignments and places unstarted dated rounds in the exact column', async () => {
    vi.mocked(axios.get).mockResolvedValue({ data: { sessions: [
        { id: 8, title: 'Geplante Runde', teaching_course_id: 18, teaching_course_date_id: 2, status: 'waiting' },
        { id: 9, title: 'Weitere Runde', teaching_course_id: 18, teaching_course_date_id: 2, status: 'waiting' },
    ] } })
    const wrapper = render()
    await wrapper.setProps({ dates: [{ id: 1, date: '2026-07-02', games: ['networks'] }, { id: 2, date: '2026-07-02', games: ['number-systems'] }] })
    await flushPromises()
    expect(wrapper.findAll('td')[0].text()).toContain('Netzwerke')
    expect(wrapper.findAll('td')[0].text()).not.toContain('Geplante Runde')
    const cards = wrapper.findAll('td')[1].findAll('.course-game-card')
    expect(cards.map(card => card.attributes('aria-label'))).toEqual([
        'Ergebnisse von Geplante Runde ansehen',
        'Ergebnisse von Weitere Runde ansehen',
    ])
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
