import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('homepage products icons', () => {
    it('uses local symbol icons for the product chips and navigation instead of mdi font icons', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/homepage/index/Products.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('<span class="picon picon--brand" aria-hidden="true">⌂</span>')
        expect(source).toContain('<span class="picon picon--nav" aria-hidden="true">←</span>')
        expect(source).toContain('<span class="picon picon--chip" :style="{ \'--icon-color\': product.accent }" aria-hidden="true">{{ product.symbol }}</span>')
        expect(source).toContain('<span class="picon picon--chip picon--feature" :style="{ \'--icon-color\': product.accent }" aria-hidden="true">{{ f.symbol }}</span>')
        expect(source).toContain('<span class="picon picon--cta" aria-hidden="true">→</span>')
    })

    it('defines sensible local symbols for the product and feature chips', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/homepage/index/Products.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("symbol: '📅'")
        expect(source).toContain("symbol: '🚀'")
        expect(source).toContain("symbol: '🤝'")
        expect(source).toContain("symbol: '📁'")
        expect(source).toContain("symbol: '🍽'")
        expect(source).toContain("{ symbol: '⏱', label: 'Zeitslot-Verwaltung' }")
        expect(source).toContain("{ symbol: '👥', label: 'Kapazitätskontrolle' }")
        expect(source).toContain("{ symbol: '📄', label: 'CSV / PDF Export' }")
    })
})
