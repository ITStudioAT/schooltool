import { afterEach, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import DecimalToHexadecimalExplanation from '@/pages/admin/teachingTools/DecimalToHexadecimalExplanation.vue'
import { radixDivisionSteps } from '@/helpers/decimalToBinaryExplanation'

const wrappers: ReturnType<typeof mount>[] = []
function render() { const wrapper = mount(DecimalToHexadecimalExplanation); wrappers.push(wrapper); return wrapper }
function button(wrapper, name) { return wrapper.findAll('button').find(item => item.text().includes(name))! }
afterEach(() => wrappers.splice(0).forEach(wrapper => wrapper.unmount()))

it.each([[0, '0'], [9, '9'], [10, 'A'], [11, 'B'], [12, 'C'], [13, 'D'], [14, 'E'], [15, 'F'], [16, '10'], [31, '1F'], [255, 'FF'], [256, '100'], [4095, 'FFF'], [4096, '1000'], [65535, 'FFFF']])('converts %s to %s and preserves positional values', (number, expected) => {
    const rows = radixDivisionSteps(number as number, 16)
    expect([...rows].reverse().map(row => row.remainder.toString(16).toUpperCase()).join('')).toBe(expected)
    expect(rows.reduce((sum, row) => sum + row.remainder * row.place, 0)).toBe(number)
    expect(rows.every(row => row.dividend === row.quotient * 16 + row.remainder && row.remainder >= 0 && row.remainder < 16)).toBe(true)
})

it('explains base 16 and A to F, pauses between phases, reads upwards and supports back and restart', async () => {
    const wrapper = render()
    expect(wrapper.get('h1').text()).toBe('Dezimal auf Hexadezimal')
    expect(wrapper.get('.binary-equation').text()).toBe('254 ÷ 16 = ?')
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('[data-testid="step-instruction"]').text()).toContain('Basis 16')
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('[data-testid="step-instruction"]').text()).toContain('254 ÷ 16 = 15, Rest 14')
    for (let step = 0; step < 6; step++) await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('[data-testid="step-instruction"]').text()).toContain('10 = A, 11 = B, 12 = C, 13 = D, 14 = E, 15 = F')
    for (let step = 0; step < 10 && !wrapper.find('#binary-phase-title').exists(); step++) await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('#binary-phase-title').text()).toBe('Das Teilen ist fertig!')
    expect(wrapper.findAll('.binary-digit--known')).toHaveLength(0)
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('[data-testid="step-instruction"]').text()).toBe('Reste von unten nach oben lesen.')
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.findAll('.binary-digit--known strong').map(item => item.text())).toEqual(['F'])
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('[data-testid="hexadecimal-result"]').text()).toBe('254 = #FE')
    expect(wrapper.get('[data-testid="hexadecimal-result"]').find('sub').exists()).toBe(false)
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('.binary-proof p').text()).toBe('15 × 16 + 14 × 1 = 254')
    expect(button(wrapper, 'Weiter').attributes('disabled')).toBeDefined()
    await button(wrapper, 'Zurück').trigger('click')
    expect(wrapper.find('.binary-proof').exists()).toBe(false)
    await button(wrapper, 'Neu starten').trigger('click')
    expect(wrapper.get('.binary-equation').text()).toBe('254 ÷ 16 = ?')
    await wrapper.get('input').setValue('256')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.get('.binary-equation').text()).toBe('256 ÷ 16 = ?')
    await wrapper.get('input').setValue('-1')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.get('[role="alert"]').text()).toContain('ganze Zahl')
})
