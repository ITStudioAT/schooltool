import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('homepage index visibility', () => {
    it('hides module cards when the user visibility flag is disabled', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/homepage/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("return this.isModuleVisible(this.registerModuleStatus)")
        expect(source).toContain("return this.isModuleVisible(this.tutoringModuleStatus)")
        expect(source).toContain("return this.isModuleVisible(this.teachingModuleStatus)")
        expect(source).toContain("return this.isModuleVisible(this.restaurantModuleStatus)")
        expect(source).toContain('v-if="canShowRegister"')
        expect(source).toContain('v-if="canShowTutoring"')
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
})
