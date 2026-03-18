import { describe, expect, it } from 'vitest'
import AdminApp from '@/pages/admin/App.vue'

describe('Admin app school logo path', () => {
    const resolveLogoSrc = (logo: unknown) => {
        return (AdminApp as any).computed.selectedSchoolLogoSrc.call({
            config: {
                selected_school: {
                    logo,
                },
            },
        })
    }

    it('returns null when no logo is configured', () => {
        expect(resolveLogoSrc(null)).toBeNull()
        expect(resolveLogoSrc('')).toBeNull()
    })

    it('maps plain logo filenames to storage images directory', () => {
        expect(resolveLogoSrc('logo_1.png')).toBe('/storage/images/logo_1.png')
    })

    it('supports prefixed image paths', () => {
        expect(resolveLogoSrc('images/logo_1.png')).toBe('/storage/images/logo_1.png')
        expect(resolveLogoSrc('logos/logo_1.png')).toBe('/storage/images/logos/logo_1.png')
    })

    it('keeps absolute storage or external urls unchanged', () => {
        expect(resolveLogoSrc('/storage/images/logo_1.png')).toBe('/storage/images/logo_1.png')
        expect(resolveLogoSrc('https://cdn.example.test/logo.png')).toBe('https://cdn.example.test/logo.png')
    })
})
