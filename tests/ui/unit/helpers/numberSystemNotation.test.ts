import { expect, it } from 'vitest'
import { formatNumberSystemText, numberSystemNotation } from '@/helpers/numberSystemNotation'

it('uses plain decimal, subscript binary and a hash prefix for hexadecimal', () => {
    expect(numberSystemNotation(10)).toEqual({ prefix: '', subscript: '', unicodeSubscript: '' })
    expect(numberSystemNotation(2)).toEqual({ prefix: '', subscript: '2', unicodeSubscript: '₂' })
    expect(numberSystemNotation(16)).toEqual({ prefix: '#', subscript: '', unicodeSubscript: '' })
    expect(formatNumberSystemText('65 = #41 = 01000001 (dual)')).toBe('65 = #41 = 01000001₂')
    expect(formatNumberSystemText('101 (dual) = 5; 0101 (dual) = #5')).toBe('101₂ = 5; 0101₂ = #5')
})
