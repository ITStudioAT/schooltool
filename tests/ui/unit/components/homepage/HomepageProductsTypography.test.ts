import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('homepage products typography', () => {
    it('uses one shared Outfit headline rule for the hero, product, and cta titles', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/homepage/index/Products.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<h1 class="phero-title" :class="{ \'is-visible\': heroVisible }">')
        expect(source).toContain('<h2 class="psection-title">{{ product.title }}</h2>')
        expect(source).toContain('<h2 class="pcta-title">Bereit für den<br />nächsten Schritt?</h2>')
        expect(source).toContain('.phero-title-line,')
        expect(source).toContain(".psection-title {")
        expect(source).toContain('.pcta-title {')
        expect(source).toContain("font-family: 'Outfit', sans-serif !important;")
    })

    it('removes the visible section transform so the product headline renders crisply', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/homepage/index/Products.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('.psection-copy.is-visible {')
        expect(source).toContain('transform: none;')
    })
})
