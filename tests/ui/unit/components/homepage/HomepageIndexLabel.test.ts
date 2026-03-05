import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('homepage tutoring label', () => {
    it('marks Schüler helfen Schülern as test version on homepage', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/homepage/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("return 'Schüler helfen Schülern (Testversion)'")
        expect(source).toContain('<h3 class="card-title">{{ tutoringDisplayName }}</h3>')
        expect(source).toContain('<span>{{ tutoringDisplayName }}</span>')
        expect(source).toContain("Nachhilfetool: this.tutoringDisplayName")
    })
})
