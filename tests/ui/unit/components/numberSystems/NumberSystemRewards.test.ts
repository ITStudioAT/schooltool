import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import axios from 'axios'
import NumberSystemPlayer from '@/pages/components/numberSystems/NumberSystemPlayer.vue'

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }))
const wrappers = []
const history = count => Array.from({ length: count }, (_, position) => ({ id: `task-${position}`, position, hints: 1, directions: ['decimal_binary'] }))
const state = (changes = {}) => ({
    id: 7, revision: 1, position: 0, total: 12, solved: false, completed: false, history: [],
    draft: {}, hints: 0, hint_texts: [], members: [], ranking: null,
    session: { title: 'Proberunde', status: 'running' },
    mission: { id: 'task-0', chapter: 0, chapter_title: 'Maschinenraum', title: 'Stelle die Zahl ein', prompt: 'Schalte die passende Zahl.', fields: [], instructions: ['Klicke auf die Schalter.'] },
    ...changes,
})
const reply = run => ({ data: { run: structuredClone(run) } })
async function render(run, props = {}) {
    vi.mocked(axios.get).mockImplementation(async () => reply(run))
    const wrapper = mount(NumberSystemPlayer, { props: { runId: 7, ...props }, attachTo: document.body, global: { stubs: { teleport: true } } })
    wrappers.push(wrapper)
    await flushPromises()
    return wrapper
}
beforeEach(() => { vi.useFakeTimers(); vi.resetAllMocks(); localStorage.clear() })
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); vi.restoreAllMocks(); vi.useRealTimers() })

it('celebrates a confirmed answer once including answers solved with a tip', async () => {
    const run = state()
    const wrapper = await render(run)
    expect(wrapper.find('.ns-reward-cinematic').exists()).toBe(false)
    vi.mocked(axios.post).mockImplementation(async () => { Object.assign(run, { solved: true, history: history(1) }); return reply(run) })
    await wrapper.get('form').trigger('submit'); await flushPromises()
    expect(wrapper.get('.ns-reward-cinematic').text()).toContain('Aufgabe 1 gelöst!')
    expect(wrapper.get('.ns-reward-cinematic').text()).toContain('1 von 12 Aufgaben geschafft.')
    await vi.advanceTimersByTimeAsync(4500); await flushPromises()
    expect(wrapper.find('.ns-reward-cinematic').exists()).toBe(false)
    await vi.advanceTimersByTimeAsync(3500); await flushPromises()
    expect(wrapper.find('.ns-reward-cinematic').exists()).toBe(false)
})

it('does not celebrate wrong answers hints saves or failed requests', async () => {
    const run = state()
    const wrapper = await render(run)
    vi.mocked(axios.post).mockImplementation(async () => reply({ ...run, revision: 2, feedback: { message: 'Prüfe noch das Ergebnis.' } }))
    await wrapper.get('form').trigger('submit'); await flushPromises()
    expect(wrapper.find('.ns-reward-cinematic').exists()).toBe(false)
    await wrapper.findAll('button').find(button => button.text() === 'Tipp 1 anzeigen').trigger('click'); await flushPromises()
    expect(wrapper.find('.ns-reward-cinematic').exists()).toBe(false)
    vi.mocked(axios.post).mockRejectedValueOnce(new Error('offline'))
    await wrapper.get('form').trigger('submit'); await flushPromises()
    expect(wrapper.find('.ns-reward-cinematic').exists()).toBe(false)
})

it('awards a chapter badge when a teammate completes the chapter and allows dismissal', async () => {
    const run = state({ history: history(2), position: 2 })
    const wrapper = await render(run)
    Object.assign(run, { history: history(3), solved: true, revision: 2 })
    await vi.advanceTimersByTimeAsync(3500); await flushPromises()
    expect(wrapper.get('.ns-reward-cinematic').text()).toContain('Maschinenraum geschafft!')
    expect(wrapper.findAll('.ns-achievements .ns-earned')).toHaveLength(1)
    await wrapper.get('[aria-label="Belohnung ausblenden"]').trigger('click'); await flushPromises()
    expect(wrapper.find('.ns-reward-cinematic').exists()).toBe(false)
    expect(wrapper.findAll('.ns-achievements .ns-earned')).toHaveLength(1)
})

it('restores earned badges without replaying animations when the game is opened again', async () => {
    const wrapper = await render(state({ history: history(6), position: 6 }))
    expect(wrapper.findAll('.ns-achievements .ns-earned')).toHaveLength(2)
    expect(wrapper.find('.ns-reward-cinematic').exists()).toBe(false)
    await vi.advanceTimersByTimeAsync(3500); await flushPromises()
    expect(wrapper.find('.ns-reward-cinematic').exists()).toBe(false)
})

it('celebrates the final activation once and preserves the completed result after dismissal', async () => {
    const run = state({ history: history(12), position: 11, solved: true })
    const wrapper = await render(run)
    vi.mocked(axios.post).mockImplementation(async () => { run.completed = true; return reply(run) })
    await wrapper.findAll('button').find(button => button.text() === 'Rechenzentrum aktivieren').trigger('click'); await flushPromises()
    expect(wrapper.get('.ns-reward-cinematic').text()).toContain('Du hast das Netz gerettet!')
    expect(wrapper.find('.ns-finale-fireworks').exists()).toBe(true)
    expect(wrapper.find('.ns-scene-radio').exists()).toBe(false)
    expect(wrapper.get('[aria-label="Fortschritt Rechenzentrum"]').findAll('span')).toHaveLength(4)
    expect(wrapper.text()).toContain('Alle 12 Aufgaben sind gelöst.')
    expect(wrapper.get('[aria-label="Deine gesammelten Abzeichen"]').findAll('.ns-earned')).toHaveLength(4)
    await vi.advanceTimersByTimeAsync(6500); await flushPromises()
    expect(wrapper.find('.ns-reward-cinematic').exists()).toBe(false)
    expect(wrapper.text()).toContain('Alle 12 Aufgaben sind gelöst.')
})

it('shows all earned badges on reopening a completed adventure without replaying the finale', async () => {
    const wrapper = await render(state({ history: history(12), position: 11, solved: true, completed: true }))
    expect(wrapper.get('[aria-label="Deine gesammelten Abzeichen"]').findAll('.ns-earned')).toHaveLength(4)
    expect(wrapper.find('.ns-reward-cinematic').exists()).toBe(false)
})

it.each([
    { total: 12, before: 0, chapter: 0, expected: 33, steps: 3, title: 'Maschinenraum' },
    { total: 18, before: 4, chapter: 0, expected: 100, steps: 5, title: 'Maschinenraum' },
    { total: 18, before: 5, chapter: 1, expected: 25, steps: 4, title: 'Fehlerarchiv' },
    { total: 30, before: 0, chapter: 0, expected: 13, steps: 8, title: 'Maschinenraum' },
    { total: 12, before: 6, chapter: 2, expected: 33, steps: 3, title: 'Tresor' },
    { total: 12, before: 9, chapter: 3, expected: 33, steps: 3, title: 'Funkzentrale' },
])('builds the current room from its own completed tasks: $total tasks, chapter $chapter, after $before', async ({ total, before, chapter, expected, steps, title }) => {
    const run = state({ total, history: history(before), position: before })
    run.mission.chapter = chapter
    const wrapper = await render(run)
    vi.mocked(axios.post).mockImplementation(async () => { Object.assign(run, { solved: true, history: history(before + 1) }); return reply(run) })
    await wrapper.get('form').trigger('submit'); await flushPromises()
    const scene = wrapper.get('[role="dialog"]')
    expect(scene.get('[role="progressbar"]').attributes('aria-valuenow')).toBe(String(expected))
    expect(scene.get('[role="progressbar"]').attributes('aria-label')).toBe(`Fortschritt ${title}`)
    expect(scene.get('[role="progressbar"]').findAll('span')).toHaveLength(steps)
    expect(scene.get('[role="progressbar"]').findAll('.ns-scene-step-online')).toHaveLength(before + 1 - Math.ceil(chapter * total / 4))
    expect(scene.text()).toContain(expected === 100 ? 'Maschinenraum online.' : ['Der Maschinenraum erwacht.', 'Das Archiv setzt sich zusammen.', 'Die Sicherungen lösen sich.', 'Das Signal wird stärker.'][chapter])
})

it('activates more machinery on the next answer and can be skipped with Escape', async () => {
    const run = state()
    const wrapper = await render(run)
    vi.mocked(axios.post).mockImplementation(async (_, payload) => {
        if (payload.action === 'advance') { Object.assign(run, { solved: false, position: 1, mission: { ...run.mission, id: 'task-1' } }) }
        else { Object.assign(run, { solved: true, history: history(run.position + 1) }) }
        return reply(run)
    })
    await wrapper.get('form').trigger('submit'); await flushPromises()
    const powered = wrapper.findAll('.ns-scene-rack .ns-module-online').length
    const continueButton = wrapper.get('[aria-label="Belohnung ausblenden"]')
    expect(document.activeElement).toBe(continueButton.element)
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' })); await flushPromises()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    await wrapper.findAll('button').find(button => button.text() === 'Nächste Aufgabe').trigger('click'); await flushPromises()
    await wrapper.get('form').trigger('submit'); await flushPromises()
    expect(wrapper.findAll('.ns-scene-rack .ns-module-online').length).toBeGreaterThan(powered)
    expect(wrapper.get('[role="progressbar"][aria-label="Fortschritt Maschinenraum"]').attributes('aria-valuenow')).toBe('67')
})

it('plays the local teacher preview even when the browser requests reduced motion and offers a pause', async () => {
    vi.spyOn(window, 'matchMedia').mockReturnValue({ matches: true, addEventListener: vi.fn(), removeEventListener: vi.fn() } as unknown as MediaQueryList)
    const run = state()
    const wrapper = await render(run, { probeId: 'local-preview', participant: 1 })
    vi.mocked(axios.post).mockImplementation(async () => { Object.assign(run, { solved: true, history: history(1) }); return reply(run) })
    await wrapper.get('form').trigger('submit'); await flushPromises()
    expect(wrapper.get('[role="dialog"]').classes()).not.toContain('ns-scene-still')
    const pause = wrapper.findAll('button').find(button => button.text() === 'Animation pausieren')
    expect(pause.attributes('aria-pressed')).toBe('true')
    await pause.trigger('click')
    expect(wrapper.get('[role="dialog"]').classes()).toContain('ns-scene-still')
    expect(wrapper.findAll('button').find(button => button.text() === 'Animation abspielen').attributes('aria-pressed')).toBe('false')
})

it('lets students explicitly start reduced motion scenes and gives the animation its full time', async () => {
    vi.spyOn(window, 'matchMedia').mockReturnValue({ matches: true, addEventListener: vi.fn(), removeEventListener: vi.fn() } as unknown as MediaQueryList)
    const run = state()
    const wrapper = await render(run)
    vi.mocked(axios.post).mockImplementation(async () => { Object.assign(run, { solved: true, history: history(1) }); return reply(run) })
    await wrapper.get('form').trigger('submit'); await flushPromises()
    expect(wrapper.get('[role="dialog"]').classes()).toContain('ns-scene-still')
    await vi.advanceTimersByTimeAsync(3300)
    await wrapper.findAll('button').find(button => button.text() === 'Animation abspielen').trigger('click'); await flushPromises()
    expect(wrapper.get('[role="dialog"]').classes()).not.toContain('ns-scene-still')
    await vi.advanceTimersByTimeAsync(1500)
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
    const continueButton = wrapper.get('[aria-label="Belohnung ausblenden"]')
    await continueButton.trigger('keydown', { key: 'Tab' })
    expect(document.activeElement.textContent).toBe('Animation pausieren')
    await wrapper.get('.ns-scene-motion').trigger('keydown', { key: 'Tab', shiftKey: true })
    expect(document.activeElement).toBe(continueButton.element)
    await vi.advanceTimersByTimeAsync(3000)
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
})

it('previews every success scene locally without saving answers or changing earned progress', async () => {
    const run = state({ history: history(2), position: 2 })
    const savedRun = structuredClone(run)
    const wrapper = await render(run, { probeId: 'local-preview', participant: 1 })
    const previews = wrapper.get('[aria-label="Erfolgsanimationen ansehen"]')
    const buttons = previews.findAll('button')
    expect(buttons.map(button => button.text().replace('▶', ''))).toEqual(['Maschinenraum', 'Fehlerarchiv', 'Tresor', 'Funkzentrale', 'Finale'])
    const colors = ['58, 216, 255', '169, 132, 255', '255, 194, 72', '71, 237, 196', '255, 210, 105']
    for (const [index, button] of buttons.entries()) {
        button.element.focus()
        await button.trigger('click'); await flushPromises()
        const scene = wrapper.get('[role="dialog"]')
        expect(scene.text()).toContain('Vorschau · ')
        expect(scene.classes()).toContain(`ns-scene-${Math.min(index, 3)}`)
        expect(scene.attributes('style')).toContain(`--scene-primary: rgb(${colors[index]})`)
        expect(scene.classes().includes('ns-scene-finale')).toBe(index === 4)
        expect(scene.find('.ns-finale-fireworks').exists()).toBe(index === 4)
        expect(scene.find('.ns-scene-radio').exists()).toBe(index === 3)
        expect(scene.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('100')
        await vi.advanceTimersByTimeAsync(3500); await flushPromises()
        expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
        await scene.get('[aria-label="Vorschau schließen"]').trigger('click'); await flushPromises()
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
        expect(document.activeElement).toBe(button.element)
    }
    await buttons[0].trigger('click'); await flushPromises()
    await vi.advanceTimersByTimeAsync(5300); await flushPromises()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(wrapper.get('progress[aria-label="Gelöste Aufgaben"]').attributes('value')).toBe('2')
    expect(wrapper.findAll('.ns-achievements .ns-earned')).toHaveLength(0)
    expect(run).toEqual(savedRun)
    expect(vi.mocked(axios.post).mock.calls.filter(([url]) => !String(url).endsWith('/presence'))).toEqual([])
})

it('keeps teacher preview controls out of student games', async () => {
    const wrapper = await render(state())
    expect(wrapper.find('[aria-label="Erfolgsanimationen ansehen"]').exists()).toBe(false)
})
