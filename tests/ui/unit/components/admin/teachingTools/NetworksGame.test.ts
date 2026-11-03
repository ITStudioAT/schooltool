import { afterEach, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import NetworksGame from '@/pages/admin/teachingTools/NetworksGame.vue'
import { curveSegments } from '@/pages/admin/teachingTools/networkCurve'

const wrappers: ReturnType<typeof mount>[] = []
afterEach(() => wrappers.splice(0).forEach(wrapper => wrapper.unmount()))

function renderGame() {
    const wrapper = mount(NetworksGame, { attachTo: document.body })
    wrappers.push(wrapper)
    return wrapper
}

function node(wrapper, x, y) {
    return wrapper.get(`svg [data-x="${x}"][data-y="${y}"]`)
}

function button(wrapper, label) {
    return wrapper.findAll('button').find(item => item.text().includes(label))!
}

it('keeps all 88 nodes visible and directly actionable without diagram labels or an X selector', () => {
    const wrapper = renderGame()

    expect(wrapper.findAll('svg [role="button"]')).toHaveLength(88)
    expect(wrapper.find('svg text, svg title, select, input, iframe').exists()).toBe(false)
    expect(wrapper.get('svg').attributes('aria-describedby')).toBe(wrapper.get('svg desc').attributes('id'))
    expect(wrapper.text()).toContain('0 von 8 Punkten')
    expect(wrapper.find('.curve-path').exists()).toBe(false)
    for (const label of ['Rückgängig', 'Punkt entfernen', 'Alles zurücksetzen']) {
        expect(button(wrapper, label).element.disabled).toBe(true)
    }
})

it('sets every node with one click, replacing only its column and drawing from the origin', async () => {
    const wrapper = renderGame()
    for (let x = 1; x <= 8; x++) {
        for (let y = -5; y <= 5; y++) {
            await node(wrapper, x, y).trigger('click')
            expect(node(wrapper, x, y).attributes('aria-pressed')).toBe('true')
            expect(wrapper.findAll('svg [aria-pressed="true"]')).toHaveLength(x)
            expect(wrapper.get('.curve-path').attributes('d')).toMatch(/^M 58,258 C /)
            const circles = node(wrapper, x, y).findAll('circle')
            expect(circles[0].attributes('cx')).toBe(String(58 + x * 99))
            expect(circles[0].attributes('cy')).toBe(String(258 - y * 44))
        }
    }
    expect(wrapper.text()).toContain('8 von 8 Punkten')
    expect(wrapper.findAll('svg [role="button"]')).toHaveLength(88)
})

it('sorts points by X and restores corrections, removals and reset with undo', async () => {
    const wrapper = renderGame()
    await node(wrapper, 8, -5).trigger('click')
    await node(wrapper, 2, 0).trigger('click')
    expect(wrapper.get('.curve-path').attributes('d')).toMatch(/256,258 C .*850,478$/)
    const originalCurve = wrapper.get('.curve-path').attributes('d')
    await node(wrapper, 2, 5).trigger('click')
    await button(wrapper, 'Rückgängig').trigger('click')
    expect(wrapper.get('.curve-path').attributes('d')).toBe(originalCurve)

    await node(wrapper, 8, -5).trigger('click')
    await button(wrapper, 'Punkt entfernen').trigger('click')
    expect(node(wrapper, 8, -5).attributes('aria-pressed')).toBe('false')
    await button(wrapper, 'Rückgängig').trigger('click')
    expect(wrapper.get('.curve-path').attributes('d')).toBe(originalCurve)
    expect(node(wrapper, 8, -5).attributes('aria-pressed')).toBe('true')

    await button(wrapper, 'Alles zurücksetzen').trigger('click')
    expect(wrapper.find('.curve-path').exists()).toBe(false)
    expect(wrapper.findAll('svg [aria-pressed="true"]')).toHaveLength(0)
    expect(wrapper.findAll('svg [role="button"]')).toHaveLength(88)
    await button(wrapper, 'Rückgängig').trigger('click')
    expect(wrapper.get('.curve-path').attributes('d')).toBe(originalCurve)
    await button(wrapper, 'Rückgängig').trigger('click')
    expect(node(wrapper, 2, 0).attributes('aria-pressed')).toBe('false')
    expect(node(wrapper, 8, -5).attributes('aria-pressed')).toBe('true')
})

it('supports Enter and Space, preserves focus, and does not consume undo for selecting an existing point', async () => {
    const wrapper = renderGame()
    node(wrapper, 3, 0).element.focus()
    await node(wrapper, 3, 0).trigger('keydown', { key: 'Enter' })
    expect(document.activeElement).toBe(node(wrapper, 3, 0).element)
    await node(wrapper, 7, -2).trigger('keydown', { key: ' ' })
    await node(wrapper, 3, 0).trigger('click')
    await button(wrapper, 'Rückgängig').trigger('click')
    expect(node(wrapper, 7, -2).attributes('aria-pressed')).toBe('false')
    expect(node(wrapper, 3, 0).attributes('aria-pressed')).toBe('true')
    await button(wrapper, 'Rückgängig').trigger('click')
    expect(wrapper.find('.curve-path').exists()).toBe(false)
    expect(button(wrapper, 'Rückgängig').element.disabled).toBe(true)
})

function sample(segment, time) {
    const remaining = 1 - time
    return [0, 1].map(axis => remaining ** 3 * segment.start[axis]
        + 3 * remaining ** 2 * time * segment.control1[axis]
        + 3 * remaining * time ** 2 * segment.control2[axis] + time ** 3 * segment.end[axis])
}

it('interpolates sparse peaks, valleys and flats through every point with smooth joins and no overshoot', () => {
    const points = [[0, 0], [1, 5], [3, -5], [4, -5], [8, 3]]
    const segments = curveSegments(points)
    expect(segments).toHaveLength(4)
    segments.forEach((segment, index) => {
        expect(sample(segment, 0)).toEqual(points[index])
        expect(sample(segment, 1)).toEqual(points[index + 1])
        for (let step = 0; step <= 100; step++) {
            const [, height] = sample(segment, step / 100)
            expect(height).toBeGreaterThanOrEqual(Math.min(points[index][1], points[index + 1][1]) - 1e-10)
            expect(height).toBeLessThanOrEqual(Math.max(points[index][1], points[index + 1][1]) + 1e-10)
        }
        if (index > 0) {
            const previous = segments[index - 1]
            const leftSlope = (previous.end[1] - previous.control2[1]) / (previous.end[0] - previous.control2[0])
            const rightSlope = (segment.control1[1] - segment.start[1]) / (segment.control1[0] - segment.start[0])
            expect(leftSlope).toBeCloseTo(rightSlope, 10)
        }
    })
    const [straight] = curveSegments([[0, 0], [8, -4]])
    expect(sample(straight, .5)).toEqual([4, -2])
    expect(curveSegments([[0, 0]])).toEqual([])
    expect(() => curveSegments([[0, 0], [0, 1]])).toThrow('strictly increasing X')
})
