import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('homepage restaurant entry', () => {
    it('keeps the restaurant card inside the app', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/homepage/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('class="tool-card card-lunch"')
        expect(source).toContain('@click="openToolForSchool(\'Restaurant\')"')
        expect(source).toContain('<h3 class="card-title">Restaurant</h3>')
        expect(source).toContain('<span class="action-text">Zum Restaurant</span>')
        expect(source).toContain('canShowRestaurant() {')
        expect(source).toContain('return true')
        expect(source).toContain('return this.config?.auth_check === true || this.moduleAllowsAccess(this.restaurantModuleStatus)')
        expect(source).not.toContain('https://cdgym.info/lunch')
    })

    it('registers the restaurant page in the homepage router', () => {
        const routerPath = resolve(process.cwd(), 'resources/routes/homepage.js')
        const source = readFileSync(routerPath, 'utf8')

        expect(source).toContain("const Restaurant = () => import('@/pages/homepage/index/Restaurant.vue')")
        expect(source).toContain("{ path: '/homepage/restaurant', component: Restaurant }")
    })

    it('renders the restaurant info text from homepage config', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/homepage/index/Restaurant.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('SchoolTool Restaurant')
        expect(source).toContain('Aktuelle Speisepl')
        expect(source).toContain("await this.homepageStore.loadConfig(schoolFromUrl, this.$route?.query?.app ?? null)")
        expect(source).toContain("return this.config?.restaurant?.user_information_intro_html || ''")
        expect(source).toContain("return Number(this.config?.restaurant?.orderable_menu_plans_count || 0)")
        expect(source).toContain("return Number(this.config?.restaurant?.visible_menu_plans_count || 0)")
        expect(source).not.toContain('Diese Seite ist aktuell eine interne Vorschau.')
    })

    it('renders restaurant eating times without seconds on the homepage', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/homepage/index/Restaurant.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('{{ formatEatingTime(et.eating_time) }} Uhr')
        expect(source).toContain("return parts.slice(0, 2).join(':')")
    })
})
