import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('homepage index visibility', () => {
    it('hides module cards when the user visibility flag is disabled', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/homepage/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("return this.isModuleVisible(this.registerModuleStatus)")
        expect(source).toContain("return this.isModuleVisible(this.teachingModuleStatus)")
        expect(source).toContain("return this.isModuleVisible(this.restaurantModuleStatus)")
        expect(source).toContain('v-if="canShowRegister"')
        expect(source).not.toContain('v-if="canShowTutoring"')
        expect(source).toContain('v-if="canShowTeaching"')
        expect(source).toContain('v-if="canShowRestaurant"')
        expect(source).toContain("label: 'Demnächst verfügbar'")
        expect(source).toContain("`${toolLabel}: Demnächst verfügbar.`")
    })

    it('adds a contrast background for logos with bright details on transparent artwork', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/homepage/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('let veryBrightPixels = 0')
        expect(source).toContain('let transparentPixels = 0')
        expect(source).toContain('const veryBrightRatio = veryBrightPixels / opaquePixels')
        expect(source).toContain('const transparentRatio = transparentPixels / (transparentPixels + opaquePixels)')
        expect(source).toContain('includeTransparentBrightDetails && transparentRatio > 0.2 && veryBrightRatio > 0.04')
        expect(source).toContain('this.logoNeedsDarkBg = this.isLogoBright(event.target)')
        expect(source).toContain('this.schoolLogoDarkFlags[schoolId] = this.isLogoBright(event.target, true)')
        expect(source).toContain('school-logo-box--dark')
        expect(source).toContain('school-select-icon--dark-logo')
    })

    it('resolves school logos from the shared logos directory', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/homepage/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("import { resolveSelectedSchoolLogoSrc } from '@/helpers/adminSchoolLogo'")
        expect(source).toContain(':src="schoolLogoSrc(school.logo)"')
        expect(source).toContain(':src="schoolLogoSrc(selected_login_school.logo)"')
        expect(source).toContain('return resolveSelectedSchoolLogoSrc(logo)')
        expect(source).not.toContain("'/storage/images/' + school.logo")
        expect(source).not.toContain("'/storage/images/' + selected_login_school.logo")
    })

    it('routes public apps to their app login pages so code login remains available', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/homepage/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('const publicToolRoute = this.publicToolRoute(tool, school)')
        expect(source).toContain("Anmeldetool: '/homepage/register'")
        expect(source).not.toContain("Nachhilfetool: '/homepage/tutoring_overview'")
        expect(source).toContain("Lehrertool: '/homepage/student'")
        expect(source).toContain("Restaurant: '/homepage/restaurant'")
        expect(source).toContain('school: school.short_name')
    })
})
