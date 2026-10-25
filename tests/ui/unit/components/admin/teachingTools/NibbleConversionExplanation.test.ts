import { afterEach, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import NibbleConversionExplanation from '@/pages/admin/teachingTools/NibbleConversionExplanation.vue'
import { nibbleConversion } from '@/helpers/nibbleConversionExplanation'

const wrappers: ReturnType<typeof mount>[] = []
function render(sourceBase) { const wrapper = mount(NibbleConversionExplanation, { props: { sourceBase } }); wrappers.push(wrapper); return wrapper }
function button(wrapper, name) { return wrapper.findAll('button').find(item => item.text().includes(name))! }
afterEach(() => wrappers.splice(0).forEach(wrapper => wrapper.unmount()))

it.each([['0', '0', 3], ['1', '1', 3], ['1111', 'F', 0], ['10000', '10', 3], ['110101', '35', 2], ['1010', 'A', 0], ['1011', 'B', 0], ['1100', 'C', 0], ['1101', 'D', 0], ['1110', 'E', 0], ['11111111', 'FF', 0], ['1111111111111111', 'FFFF', 0]])('groups binary %s into hexadecimal %s with %s padding bits', (input, hex, padding) => {
    const result = nibbleConversion(input, 2)!
    expect(result.hex).toBe(hex)
    expect(result.padding).toBe(padding)
    expect(result.groups.every(group => group.binary.length === 4)).toBe(true)
    expect(Number.parseInt(result.binary, 2)).toBe(Number.parseInt(input as string, 2))
})

it.each(['0', '1', '10', 'A', 'B', 'C', 'D', 'E', 'F', 'AF', '100', 'FFFF'])('expands hexadecimal #%s into exactly four bits per digit', hex => {
    const result = nibbleConversion('#' + hex, 16)!
    expect(result.groups.map(group => group.hex).join('')).toBe(hex)
    expect(result.binary).toHaveLength(hex.length * 4)
    expect(Number.parseInt(result.binary, 2)).toBe(Number.parseInt(hex, 16))
})

it('rejects invalid or oversized source numbers and accepts grouped bits and lowercase hex', () => {
    expect(nibbleConversion('11 0101', 2)?.hex).toBe('35')
    expect(nibbleConversion('#af', 16)?.binary).toBe('10101111')
    for (const input of ['', '102', '-1', '1'.repeat(17)]) expect(nibbleConversion(input, 2)).toBeNull()
    for (const input of ['', '0xAF', '#G', '#10000', '##AF']) expect(nibbleConversion(input, 16)).toBeNull()
})

it('starts on the right, visibly adds only leading zeros, converts each group and navigates back', async () => {
    const wrapper = render(2)
    expect(wrapper.get('h1').text()).toBe('Dual auf Hexadezimal')
    expect(wrapper.find('.grouping-group').exists()).toBe(false)
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('.grouping-group--active .grouping-bits').text()).toBe('01012')
    expect(wrapper.find('.grouping-padding').exists()).toBe(false)
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('.grouping-padding').text()).toBe('00')
    expect(wrapper.get('[data-testid="grouping-instruction"]').text()).toContain('Ergänze links 2 Nullen')
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('.grouping-group--active').text()).toContain('#3')
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('.grouping-group--active').text()).toContain('#5')
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('[data-testid="grouping-result"]').text()).toBe('1101012 = #35')
    expect(button(wrapper, 'Weiter').attributes('disabled')).toBeDefined()
    await button(wrapper, 'Zurück').trigger('click')
    expect(wrapper.find('[data-testid="grouping-result"]').exists()).toBe(false)
    await button(wrapper, 'Neu starten').trigger('click')
    expect(button(wrapper, 'Zurück').attributes('disabled')).toBeDefined()
    await wrapper.get('input').setValue('102')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.get('[role="alert"]').text()).toContain('0 und 1')
})

it('maps each hex digit to four bits, preserves zero groups and accepts a new example', async () => {
    const wrapper = render(16)
    expect(wrapper.get('h1').text()).toBe('Hexadezimal auf Dual')
    await button(wrapper, 'Weiter').trigger('click')
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('.grouping-group--active .grouping-bits').text()).toBe('10102')
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('.grouping-group--active .grouping-bits').text()).toBe('11112')
    await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('[data-testid="grouping-result"]').text()).toBe('#AF = 1010 11112')
    await wrapper.get('input').setValue('#10')
    await wrapper.get('form').trigger('submit')
    for (let index = 0; index < 4; index++) await button(wrapper, 'Weiter').trigger('click')
    expect(wrapper.get('[data-testid="grouping-result"]').text()).toBe('#10 = 0001 00002')
})
