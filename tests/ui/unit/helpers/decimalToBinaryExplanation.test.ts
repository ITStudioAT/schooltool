import { expect, it } from 'vitest'
import { decimalDivisionSteps, parseDecimalExample } from '@/helpers/decimalToBinaryExplanation'

it.each([0, 1, 2, 13, 25, 128, 255, 1024, 65535])('builds exact division and remainder steps for %s', number => {
    const rows = decimalDivisionSteps(number)
    expect([...rows].reverse().map(row => row.remainder).join('')).toBe(number.toString(2))
    expect(rows.at(-1)?.quotient).toBe(0)
    rows.forEach((row, index) => {
        expect(row.dividend).toBe(row.quotient * 2 + row.remainder)
        expect(row.place).toBe(2 ** index)
        if (index) expect(row.dividend).toBe(rows[index - 1].quotient)
    })
})

it.each(['', '-1', '1.5', '1e3', '0x10', '65536', 'Infinity'])('rejects invalid input %s', value => {
    expect(parseDecimalExample(value)).toBeNull()
})

it('accepts whole decimal input and rejects invalid numbers at the algorithm boundary', () => {
    expect(parseDecimalExample(' 0013 ')).toBe(13)
    expect(parseDecimalExample('0')).toBe(0)
    expect(() => decimalDivisionSteps(1.5)).toThrow(RangeError)
    expect(() => decimalDivisionSteps(-1)).toThrow(RangeError)
})
