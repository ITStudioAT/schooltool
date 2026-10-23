import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import axios from 'axios'
import { createNumberSystemSound } from '@/pages/components/numberSystems/numberSystemSound'
import NumberSystemPlayer from '@/pages/components/numberSystems/NumberSystemPlayer.vue'

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }))
const instances = [], wrappers = []
const parameter = () => ({ value: 0, setValueAtTime: vi.fn(), exponentialRampToValueAtTime: vi.fn() })
class TestAudioContext {
    state = 'suspended'; currentTime = 10; destination = {}; sources = []; gains = []
    constructor() { instances.push(this) }
    createGain() { const gain = { gain: parameter(), connect: vi.fn(), disconnect: vi.fn() }; this.gains.push(gain); return gain }
    createOscillator() { const source = { frequency: parameter(), connect: vi.fn(), disconnect: vi.fn(), start: vi.fn(), stop: vi.fn(), onended: null }; this.sources.push(source); return source }
    resume = vi.fn(async () => { this.state = 'running' })
    close = vi.fn(async () => { this.state = 'closed' })
}
beforeEach(() => { vi.useFakeTimers(); vi.resetAllMocks(); instances.length = 0; localStorage.clear(); vi.stubGlobal('AudioContext', TestAudioContext) })
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); vi.unstubAllGlobals(); vi.useRealTimers() })

it('creates no audio before activation and reuses the unlocked browser context', async () => {
    const sound = createNumberSystemSound()
    expect(await sound.play({ chapter: 0, chapterProgress: 33 })).toBe(false)
    expect(instances).toHaveLength(0)
    expect(await sound.unlock()).toBe(true)
    expect(await sound.unlock()).toBe(true)
    expect(instances).toHaveLength(1)
    expect(instances[0].resume).toHaveBeenCalledOnce()
    expect(instances[0].gains[0].gain.value).toBe(2)
    expect(await sound.play({ chapter: 0, chapterProgress: 33 })).toBe(true)
    expect(instances[0].sources).toHaveLength(5)
    for (const source of instances[0].sources) {
        expect(source.start).toHaveBeenCalledOnce()
        expect(source.stop.mock.calls[0][0]).toBeLessThan(12)
    }
    sound.dispose()
    expect(instances[0].close).toHaveBeenCalledOnce()
})

it('adds a resolved finale when the room is complete and stops earlier sounds before replay', async () => {
    const sound = createNumberSystemSound()
    await sound.unlock()
    await sound.play({ chapter: 0, chapterProgress: 33 })
    const previous = [...instances[0].sources]
    await sound.play({ chapter: 0, chapterProgress: 100 })
    expect(instances[0].sources).toHaveLength(14)
    previous.forEach(source => expect(source.stop).toHaveBeenLastCalledWith())
    sound.stop()
    instances[0].sources.forEach(source => expect(source.stop).toHaveBeenLastCalledWith())
    sound.dispose()
})

it('cancels pending playback when muted before the audio context finishes activating', async () => {
    let finishUnlock
    const sound = createNumberSystemSound()
    const unlocked = sound.unlock()
    await unlocked
    instances[0].state = 'suspended'
    instances[0].resume.mockImplementationOnce(() => new Promise(resolve => { finishUnlock = () => { instances[0].state = 'running'; resolve() } }))
    const pending = sound.unlock()
    const playback = sound.play({ chapter: 0, chapterProgress: 33 })
    sound.stop(); finishUnlock()
    await pending
    expect(await playback).toBe(false)
    expect(instances[0].sources).toHaveLength(0)
    sound.dispose()
})

it('keeps the game usable when Web Audio is unavailable', async () => {
    vi.stubGlobal('AudioContext', undefined); vi.stubGlobal('webkitAudioContext', undefined)
    const sound = createNumberSystemSound()
    expect(sound.available).toBe(false)
    expect(await sound.unlock()).toBe(false)
    expect(await sound.play({ chapter: 0, chapterProgress: 100 })).toBe(false)
    sound.stop(); sound.dispose()
})

it.each([
    { chapter: 0, firstFrequency: 55, voices: 5, finishedVoices: 9 },
    { chapter: 1, firstFrequency: 523.2511306011972, voices: 4, finishedVoices: 9 },
    { chapter: 2, firstFrequency: 130, voices: 4, finishedVoices: 8 },
    { chapter: 3, firstFrequency: 520, voices: 3, finishedVoices: 7 },
])('plays a distinct bounded signal and fuller completion for chapter $chapter', async ({ chapter, firstFrequency, voices, finishedVoices }) => {
    const sound = createNumberSystemSound()
    await sound.unlock()
    expect(await sound.play({ chapter, chapterProgress: 33 })).toBe(true)
    expect(instances[0].sources).toHaveLength(voices)
    expect(instances[0].sources[0].frequency.setValueAtTime.mock.calls[0][0]).toBeCloseTo(firstFrequency)
    await sound.play({ chapter, chapterProgress: 100 })
    expect(instances[0].sources).toHaveLength(voices + finishedVoices)
    instances[0].sources.forEach(source => expect(source.stop.mock.calls[0][0]).toBeLessThan(12))
    sound.stop()
    instances[0].sources.forEach(source => expect(source.stop).toHaveBeenLastCalledWith())
    sound.dispose()
})

it('plays a distinct longer firework fanfare only when the entire adventure is activated', async () => {
    const sound = createNumberSystemSound()
    await sound.unlock()
    await sound.play({ chapter: 3, chapterProgress: 100, kind: 'complete' })
    expect(instances[0].sources).toHaveLength(30)
    expect(instances[0].sources[0].frequency.setValueAtTime).toHaveBeenCalledWith(180, 10.02)
    const ends = instances[0].sources.map(source => source.stop.mock.calls[0][0])
    expect(Math.max(...ends)).toBeGreaterThan(14)
    expect(Math.max(...ends)).toBeLessThan(15)
    expect(await sound.play({ chapter: 4, chapterProgress: 100 })).toBe(false)
    sound.dispose()
})

it('plays only confirmed answers once and respects muting across following answers', async () => {
    const run = { id: 7, revision: 1, position: 0, total: 12, solved: false, completed: false, history: [], draft: {}, hints: 0, hint_texts: [], members: [], ranking: null,
        session: { title: 'Maschinenraum', status: 'running' }, mission: { id: 'task-0', chapter: 0, chapter_title: 'Maschinenraum', title: 'Aufgabe', fields: [] } }
    const reply = () => ({ data: { run: structuredClone(run) } })
    vi.mocked(axios.get).mockImplementation(async () => reply())
    let correct = false
    vi.mocked(axios.post).mockImplementation(async (_, payload) => {
        if (payload?.action === 'answer' && correct) { run.solved = true; run.history.push({ hints: 0, directions: [] }) }
        if (payload?.action === 'advance') { run.solved = false; run.position++; run.mission.id = `task-${run.position}` }
        return reply()
    })
    const wrapper = mount(NumberSystemPlayer, { props: { runId: 7 }, global: { stubs: { teleport: true } } }); wrappers.push(wrapper)
    await flushPromises()
    await wrapper.get('form').trigger('submit'); await flushPromises()
    expect(instances[0].sources).toHaveLength(0)
    correct = true
    await wrapper.get('form').trigger('submit'); await flushPromises()
    expect(instances[0].sources).toHaveLength(5)
    await vi.advanceTimersByTimeAsync(3500); await flushPromises()
    expect(instances[0].sources).toHaveLength(5)
    await wrapper.get('[role="dialog"]').findAll('button').find(button => button.text() === 'Ton ausschalten').trigger('click'); await flushPromises()
    expect(localStorage.getItem('schooltool-number-system-sound')).toBe('off')
    instances[0].sources.forEach(source => expect(source.stop).toHaveBeenLastCalledWith())
    await wrapper.get('[aria-label="Belohnung ausblenden"]').trigger('click'); await flushPromises()
    await wrapper.findAll('button').find(button => button.text() === 'Nächste Aufgabe').trigger('click'); await flushPromises()
    await wrapper.get('form').trigger('submit'); await flushPromises()
    expect(instances[0].sources).toHaveLength(5)
    wrapper.unmount(); wrappers.length = 0
    expect(instances[0].close).toHaveBeenCalledOnce()
})

it('unlocks sound from a teacher preview click and keeps later previews muted when switched off', async () => {
    const run = { id: 7, revision: 1, position: 0, total: 12, solved: false, completed: false, history: [], draft: {}, hints: 0, hint_texts: [], members: [], ranking: null,
        session: { title: 'Selbsttest', status: 'running' }, mission: { id: 'task-0', chapter: 0, chapter_title: 'Maschinenraum', title: 'Aufgabe', fields: [] } }
    vi.mocked(axios.get).mockResolvedValue({ data: { run } })
    const wrapper = mount(NumberSystemPlayer, { props: { runId: 7, probeId: 'local-preview', participant: 1 }, global: { stubs: { teleport: true } } }); wrappers.push(wrapper)
    await flushPromises()
    expect(instances).toHaveLength(0)
    await wrapper.get('[aria-label="Erfolgsanimationen ansehen"]').findAll('button')[1].trigger('click'); await flushPromises()
    expect(instances).toHaveLength(1)
    expect(instances[0].sources).toHaveLength(9)
    expect(wrapper.get('[role="dialog"]').text()).toContain('Ton ausschalten')
    await wrapper.get('[role="dialog"]').findAll('button').find(button => button.text() === 'Ton ausschalten').trigger('click'); await flushPromises()
    instances[0].sources.forEach(source => expect(source.stop).toHaveBeenLastCalledWith())
    await wrapper.get('[aria-label="Vorschau schließen"]').trigger('click'); await flushPromises()
    await wrapper.get('[aria-label="Erfolgsanimationen ansehen"]').findAll('button')[4].trigger('click'); await flushPromises()
    expect(instances[0].sources).toHaveLength(9)
    expect(axios.post).not.toHaveBeenCalled()
})
