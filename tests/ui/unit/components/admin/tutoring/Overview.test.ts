import { readFileSync, readdirSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import homepageRouter from '../../../../../../resources/routes/homepage.js'
import Products from '@/pages/homepage/index/Products.vue'
import homepageTheme from '../../../../../../resources/plugins/homepage.js'

describe('removed tutoring overview', () => {
    it('removes tutoring routes while retaining the other public modules', () => {
        const paths = homepageRouter.getRoutes().map((route) => route.path)

        expect(paths.filter((path) => path.includes('tutoring'))).toEqual([])
        expect(paths).toEqual(expect.arrayContaining([
            '/homepage/register',
            '/student/overview',
            '/students-timetables/overview',
            '/homepage/restaurant',
            '/homepage/cashier',
        ]))
    })

    it('retains the other advertised products', () => {
        const data = (Products as any).data.call({})

        expect(data.products.map((product: { key: string }) => product.key)).not.toContain('tutoring')
        expect(data.products.map((product: { key: string }) => product.key)).toEqual(expect.arrayContaining([
            'register', 'teaching', 'materials', 'restaurant',
        ]))
    })

    it('preserves the cashier colors independently of the removed module', () => {
        const colors = homepageTheme.theme.themes.value.light.colors

        expect(colors.cashier_background).toBe('#5c6c62')
        expect(colors.cashier_text).toBe('#fbf0d4')
        expect(Object.keys(colors).some((key) => key.startsWith('tutoring_'))).toBe(false)
    })

    it.each(['', 'en'])('removes the documentation product card from HTML and hydration bundles (%s)', (locale) => {
        const directory = resolve(process.cwd(), 'public/documentation', locale)
        const bundlesDirectory = resolve(directory, 'assets/js')
        const homepageBundles = readdirSync(bundlesDirectory).filter((name) => /^c4f5d8e4.*\.js$/u.test(name))

        expect(homepageBundles.length).toBeGreaterThan(0)

        for (const path of [resolve(directory, 'index.html'), ...homepageBundles.map((name) => resolve(bundlesDirectory, name))]) {
            const source = readFileSync(path, 'utf8')

            expect(source, path).not.toContain('Nachhilfetool')
            expect(source, path).toContain('Anmeldetool')
        }
    })
})
