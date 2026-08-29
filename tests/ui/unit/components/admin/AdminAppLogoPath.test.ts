import { describe, expect, it } from 'vitest'
import { resolveSelectedSchoolLogoSrc } from '@/helpers/adminSchoolLogo'

describe('Admin app school logo path', () => {
    it('returns null when no logo is configured', () => {
        expect(resolveSelectedSchoolLogoSrc(null)).toBeNull()
        expect(resolveSelectedSchoolLogoSrc('')).toBeNull()
    })

    it('maps plain logo filenames to the persisted school logo directory', () => {
        expect(resolveSelectedSchoolLogoSrc('logo_1.png')).toBe('/storage/images/logos/logo_1.png')
    })

    it('supports prefixed image paths', () => {
        expect(resolveSelectedSchoolLogoSrc('images/logo_1.png')).toBe('/storage/images/logo_1.png')
        expect(resolveSelectedSchoolLogoSrc('logos/logo_1.png')).toBe('/storage/images/logos/logo_1.png')
        expect(resolveSelectedSchoolLogoSrc('storage/images/logo_1.png')).toBe('/storage/images/logo_1.png')
    })

    it('keeps absolute storage or external urls unchanged', () => {
        expect(resolveSelectedSchoolLogoSrc('/storage/images/logo_1.png')).toBe('/storage/images/logo_1.png')
        expect(resolveSelectedSchoolLogoSrc('https://cdn.example.test/logo.png')).toBe('https://cdn.example.test/logo.png')
    })

    it('normalizes whitespace and windows path separators', () => {
        expect(resolveSelectedSchoolLogoSrc(' logos\\logo_1.png ')).toBe('/storage/images/logos/logo_1.png')
    })
})
