import { describe, expect, it } from 'vitest'
import LicenceSchools from '@/pages/admin/superAdmin/components/LicenceSchools.vue'

describe('Super admin licence assignments', () => {
    it('sorts licences alphabetically by name', () => {
        const items = [
            { name: 'Z-Lizenz' },
            { name: 'A-Lizenz' },
            { name: 'M-Lizenz' },
        ]

        const sorted = (LicenceSchools as any).methods.sortedLicences.call({}, items)
        expect(sorted.map((item: { name: string }) => item.name)).toEqual(['A-Lizenz', 'M-Lizenz', 'Z-Lizenz'])
    })

    it('detects school licence as not needed from direct flag and model', () => {
        const methods = (LicenceSchools as any).methods
        const ctx = {
            normalizeLicenceModel(licenceModel: unknown) {
                return methods.normalizeLicenceModel.call(this, licenceModel)
            },
            toBool: methods.toBool,
        }

        const fromFlag = methods.isSchoolLicenceNotNeeded.call(ctx, { school_licence_required: false })
        const fromModel = methods.isSchoolLicenceNotNeeded.call(ctx, { licence_model: JSON.stringify({ school_licence_required: false }) })

        expect(fromFlag).toBe(true)
        expect(fromModel).toBe(true)
    })

    it('evaluates licence active status based on valid_until date', () => {
        const methods = (LicenceSchools as any).methods
        const ctx = {
            isSchoolLicenceNotNeeded: () => false,
            localDateKey: () => '2026-03-02',
        }

        const active = methods.isLicenceActive.call(ctx, { valid_until: '2026-03-03' })
        const expired = methods.isLicenceActive.call(ctx, { valid_until: '2026-03-01' })

        expect(active).toBe(true)
        expect(expired).toBe(false)
    })
})
