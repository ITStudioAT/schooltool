import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

function readSource(path: string): string {
    return readFileSync(resolve(process.cwd(), path), 'utf8')
}

describe('frontend bundle boundaries', () => {
    it('lets the Vuetify Vite plugin tree-shake components and directives', () => {
        const pluginSource = readSource('resources/plugins/admin.js')
        const viteSource = readSource('vite.config.js')

        expect(pluginSource).toContain("import { VDateInput } from 'vuetify/labs/VDateInput'")
        expect(pluginSource).toContain('components: {\n        VDateInput,\n    }')
        expect(pluginSource).not.toContain("import * as components from 'vuetify/components'")
        expect(pluginSource).not.toContain("import * as directives from 'vuetify/directives'")
        expect(pluginSource).not.toContain('...components')
        expect(pluginSource).not.toContain('directives,')
        expect(viteSource).toContain('autoImport: true')
        expect(viteSource).not.toContain("return 'vendor-vuetify'")
    })

    it('keeps the restaurant overview eager and lazy loads inactive sections', () => {
        const source = readSource('resources/js/pages/admin/restaurant/Restaurant.vue')
        const lazySections = [
            'Foods',
            'Menus',
            'MenuPlans',
            'Reports',
            'RestaurantSepa',
            'Users',
            'Settings',
            'CdgymLegacy',
        ]

        expect(source).toContain("import Overview from './components/Overview.vue'")

        for (const section of lazySections) {
            expect(source).toContain(
                `const ${section} = defineAsyncComponent(() => import('./components/${section}.vue'))`,
            )
            expect(source).not.toContain(`import ${section} from './components/${section}.vue'`)
        }
    })

    it('loads the register rich text editor only while editing', () => {
        const source = readSource(
            'resources/js/pages/admin/registerSystem/components/RegisterSystem/Registers.vue',
        )

        expect(source).toContain(
            "const ItsRichTextEditor = defineAsyncComponent(() => import('@/components/ItsRichTextEditor.vue'))",
        )
        expect(source).toContain("v-if=\"action === 'edit_register' || action === 'create_register'\"")
        expect(source).not.toContain("import ItsRichTextEditor from '@/components/ItsRichTextEditor.vue'")
    })

    it('keeps the curriculum overview eager and lazy loads detail features', () => {
        const source = readSource('resources/js/pages/admin/teaching/curricula/Curricula.vue')

        expect(source).toContain("import CurriculaOverview from './CurriculaOverview.vue'")
        expect(source).toContain(
            "const CurriculumDetail = defineAsyncComponent(() => import('./CurriculumDetail.vue'))",
        )
        expect(source).toContain(
            "const CurriculaPrint = defineAsyncComponent(() => import('./CurriculaPrint.vue'))",
        )
        expect(source).not.toContain("import CurriculumDetail from './CurriculumDetail.vue'")
        expect(source).not.toContain("import CurriculaPrint from './CurriculaPrint.vue'")
    })
})
