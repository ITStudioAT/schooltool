import { describe, expect, it } from 'vitest'
import { iban, validateIban } from '@/helpers/rules'

describe('IBAN validation helper', () => {
    it('normalizes valid AT and DE iban values', () => {
        expect(validateIban(' at61 1904 3002 3457 3201 ')).toEqual({
            isValid: true,
            normalizedIban: 'AT611904300234573201',
            countryCode: 'AT',
            error: null,
        })

        expect(validateIban('DE89370400440532013000')).toEqual({
            isValid: true,
            normalizedIban: 'DE89370400440532013000',
            countryCode: 'DE',
            error: null,
        })
    })

    it('rejects a wrong checksum, length and invalid characters', () => {
        expect(validateIban('AT611904300234573202')).toMatchObject({
            isValid: false,
            countryCode: 'AT',
            error: 'Die IBAN-Prüfziffer ist ungültig.',
        })

        expect(validateIban('DE8937040044053201300')).toMatchObject({
            isValid: false,
            countryCode: 'DE',
            error: 'Die IBAN-Länge ist für dieses Land ungültig.',
        })

        expect(validateIban('AT61!904300234573201')).toMatchObject({
            isValid: false,
            countryCode: 'AT',
            error: 'Die IBAN darf nur Buchstaben und Ziffern enthalten.',
        })
    })

    it('returns a Vuetify rule that uses the same validator', () => {
        const rule = iban()

        expect(rule('AT611904300234573201')).toBe(true)
        expect(rule('AT611904300234573202')).toBe('Die IBAN-Prüfziffer ist ungültig.')
    })
})
