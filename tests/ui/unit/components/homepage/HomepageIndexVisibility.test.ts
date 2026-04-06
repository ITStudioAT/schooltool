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
})
