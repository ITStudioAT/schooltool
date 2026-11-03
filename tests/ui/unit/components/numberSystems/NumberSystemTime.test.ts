import { afterEach, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { reactive } from 'vue'
import NumberSystemClock from '@/pages/components/numberSystems/NumberSystemClock.vue'
import NumberSystemRanking from '@/pages/components/numberSystems/NumberSystemRanking.vue'
import NumberSystemStartOptions from '@/pages/components/numberSystems/NumberSystemStartOptions.vue'
import NumberSystemPresence from '@/pages/components/numberSystems/NumberSystemPresence.vue'

const wrappers = []
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); vi.useRealTimers() })

it('shows connection per person with the last contact and without claiming a browser was closed', () => {
    const wrapper = mount(NumberSystemPresence, { props: { people: [
        { name: 'Person A', status: 'connected', last_seen_at: '2026-10-17T10:00:00Z' },
        { name: 'Person B', status: 'disconnected', last_seen_at: '2026-10-17T09:58:00Z' },
    ] } }); wrappers.push(wrapper)
    expect(wrapper.text()).toContain('Person AVerbunden')
    expect(wrapper.text()).toContain('Person BVerbindung unterbrochen')
    expect(wrapper.text()).toContain('Letzter Kontakt')
    expect(wrapper.text()).not.toContain('Browser geschlossen')
})

it('starts without time pressure and keeps countdown ranking and content independent', async () => {
    const model = reactive({ planned_minutes: 40, difficulty: 3, timer_mode: 'none', countdown_minutes: null, ranking_enabled: false })
    const wrapper = mount(NumberSystemStartOptions, { props: { model } }); wrappers.push(wrapper)
    expect(wrapper.find('input[type="number"]').exists()).toBe(false)
    await wrapper.get('select').setValue('countdown')
    expect(wrapper.get('input[type="number"]').element.value).toBe('40')
    await wrapper.get('input[type="number"]').setValue('30')
    expect(model.ranking_enabled).toBe(false)
    await wrapper.get('input[type="checkbox"]').setValue(true)
    await wrapper.get('select').setValue('none')
    expect(model).toMatchObject({ planned_minutes: 40, difficulty: 3, timer_mode: 'none', countdown_minutes: 30, ranking_enabled: true })
})

it('ticks from the server snapshot stops on pause and completion and permits visible overtime', async () => {
    vi.useFakeTimers()
    const wrapper = mount(NumberSystemClock, { props: { clock: { mode: 'countdown', elapsed_seconds: 58, limit_seconds: 60, ticking: true } } }); wrappers.push(wrapper)
    expect(wrapper.text()).toContain('00:02')
    await vi.advanceTimersByTimeAsync(3000)
    expect(wrapper.text()).toContain('00:00')
    expect(wrapper.text()).toContain('Verstrichen 01:01')
    expect(wrapper.text()).toContain('Der Fortschritt bleibt erhalten')
    await wrapper.setProps({ clock: { mode: 'elapsed', elapsed_seconds: 61, ticking: false } })
    await vi.advanceTimersByTimeAsync(10000)
    expect(wrapper.text()).toContain('01:01')
    expect(wrapper.text()).not.toContain('01:11')
    await wrapper.setProps({ clock: { mode: 'none', elapsed_seconds: null, ticking: false } })
    expect(wrapper.find('[role="timer"]').exists()).toBe(false)
})

it('shows ranking only when enabled and waits for a round end before listing peers', async () => {
    const wrapper = mount(NumberSystemRanking, { props: { ranking: null } }); wrappers.push(wrapper)
    expect(wrapper.find('section').exists()).toBe(false)
    await wrapper.setProps({ ranking: { final: false, rules: 'Abschluss vor Zeit', entries: [] } })
    expect(wrapper.find('ol').exists()).toBe(false)
    await wrapper.setProps({ ranking: { final: true, rules: 'Abschluss vor Zeit', entries: [{ id: 7, label: 'Team Sieben', own: true, rank: 1, solved: 12, total: 12, completed: true, seconds: 100 }] } })
    expect(wrapper.text()).toContain('Team Sieben · euer Spielstand')
    expect(wrapper.text()).toContain('12 / 12 bestätigt · abgeschlossen · 01:40')
})
