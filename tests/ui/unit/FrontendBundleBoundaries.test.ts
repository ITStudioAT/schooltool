import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import { loadConfigFromFile } from 'vite'
import { rollup } from 'rollup'

function readSource(path: string): string {
    return readFileSync(resolve(process.cwd(), path), 'utf8')
}

describe('frontend bundle boundaries', () => {
    it('emits the PDF worker as JavaScript while preserving its module and other asset types', async () => {
        const configuration = await loadConfigFromFile({ command: 'build', mode: 'production' }, resolve('vite.config.js'))
        const workerSource = readSource('node_modules/pdfjs-dist/build/pdf.worker.min.mjs')
        const bundle = await rollup({
            input: 'preview-entry',
            plugins: [{
                name: 'pdf-worker-build-test',
                resolveId: (id) => id,
                load() {
                    const workerReference = this.emitFile({ type: 'asset', name: 'pdf.worker.min.mjs', source: workerSource })
                    this.emitFile({ type: 'asset', name: 'logo.svg', source: '<svg />' })

                    return `export default import.meta.ROLLUP_FILE_URL_${workerReference};`
                },
            }],
        })

        try {
            const { output } = await bundle.generate({
                ...configuration.config.build.rollupOptions.output,
                format: 'es',
            })
            const worker = output.find((file) => file.type === 'asset' && file.names.includes('pdf.worker.min.mjs'))
            const entry = output.find((file) => file.type === 'chunk' && file.isEntry)

            expect(worker.fileName).toMatch(/^assets\/pdf\.worker\.min-[\w-]+\.js$/)
            expect(worker.source).toBe(workerSource)
            expect(entry.code).toContain(worker.fileName)
            expect(output.some((file) => file.fileName.endsWith('.mjs'))).toBe(false)
            expect(output.some((file) => /^assets\/logo-[\w-]+\.svg$/.test(file.fileName))).toBe(true)
        } finally {
            await bundle.close()
        }
    })

    it('gives production builds reliable heap headroom without returning to a 4 GB budget', () => {
        const packageConfiguration = JSON.parse(readSource('package.json'))
        const viteSource = readSource('vite.config.js')

        expect(packageConfiguration.scripts.build).toContain('--max-old-space-size=2048')
        expect(packageConfiguration.scripts.build).not.toContain('--max-old-space-size=4096')
        expect(viteSource).toContain('reportCompressedSize: false')
    })

    it('reads production Echo settings at runtime instead of baking them into the bundle', () => {
        const adminEntrySource = readSource('resources/js/apps/admin.js')
        const adminViewSource = readSource('resources/views/admin.blade.php')
        const packagedAdminViewSource = readSource('resources/views/vendor/spa/admin.blade.php')

        expect(adminEntrySource).toContain('window.schooltoolEchoEnvironment ?? {}')
        expect(adminEntrySource).not.toContain('import.meta.env')
        expect(adminViewSource).toContain('window.schooltoolEchoEnvironment')
        expect(packagedAdminViewSource).toContain('window.schooltoolEchoEnvironment')
    })

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

    it('lazy loads inactive materials screens and heavy dialogs', () => {
        const pageSource = readSource('resources/js/pages/admin/materials/Materials.vue')
        const overviewSource = readSource(
            'resources/js/pages/admin/materials/components/views/MaterialsOverviewView.vue',
        )

        for (const view of [
            'MaterialsFreigabeView',
            'MaterialsOverviewView',
            'MaterialsNewView',
            'MaterialsPermissionsView',
        ]) {
            expect(pageSource).toContain(`const ${view} = defineAsyncComponent(() => import(`)
        }

        expect(overviewSource).not.toContain("import vueFilePond from 'vue-filepond/dist/vue-filepond.js'")
        expect(overviewSource).toContain(
            "const MaterialsCreateInlineForm = defineAsyncComponent(() => import('../forms/MaterialsCreateInlineForm.vue'))",
        )
        expect(overviewSource).toContain(
            "const MaterialDetailDialog = defineAsyncComponent(() => import('../overview/dialogs/MaterialDetailDialog.vue'))",
        )
        expect(overviewSource).toContain('<MaterialDetailDialog\n        v-if="detailDialogOpen"')

        for (const component of [
            'MaterialsOverviewAlphaList',
            'MaterialsOverviewGrid',
            'MaterialsOverviewList',
            'MaterialsSubjectsContentsTree',
        ]) {
            expect(overviewSource).toContain(
                `const ${component} = defineAsyncComponent(() => import('../overview/${component}.vue'))`,
            )
            expect(overviewSource).not.toContain(
                `import ${component} from '../overview/${component}.vue'`,
            )
        }
    })

    it('loads curriculum PDF features and the PDF worker on demand', () => {
        const detailSource = readSource(
            'resources/js/pages/admin/teaching/curricula/CurriculumDetail.vue',
        )
        const previewSource = readSource(
            'resources/js/pages/admin/teaching/curricula/CurriculumPdfPreview.vue',
        )

        expect(detailSource).toContain(
            "const CurriculumPdfPreview = defineAsyncComponent(() => import('@/pages/admin/teaching/curricula/CurriculumPdfPreview.vue'))",
        )
        expect(detailSource).toContain(
            "const CurriculumUnitFilesDialog = defineAsyncComponent(() => import('@/pages/admin/teaching/curricula/CurriculumUnitFilesDialog.vue'))",
        )
        expect(detailSource).not.toContain(
            "import CurriculumPdfPreview from '@/pages/admin/teaching/curricula/CurriculumPdfPreview.vue'",
        )
        expect(previewSource).toContain(
            "import('pdfjs-dist/build/pdf.worker.min.mjs?url')",
        )
        expect(previewSource).not.toContain(
            "import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url'",
        )
    })

    it('loads the rich text editor separately from feature bundles', () => {
        const editorConsumers = [
            'resources/js/pages/admin/materials/components/forms/MaterialsCreateInlineForm.vue',
            'resources/js/pages/admin/restaurant/components/Settings.vue',
            'resources/js/pages/admin/restaurant/components/Sepa.vue',
            'resources/js/pages/admin/teaching/overview/components/CourseDates.vue',
            'resources/js/pages/admin/teaching/overview/components/CourseInfos.vue',
            'resources/js/pages/admin/teaching/overview/components/CourseStudent.vue',
            'resources/js/pages/admin/teaching/overview/components/CourseTable.vue',
        ]

        for (const path of editorConsumers) {
            const source = readSource(path)

            expect(source).toContain(
                "const ItsRichTextEditor = defineAsyncComponent(() => import('@/components/ItsRichTextEditor.vue'))",
            )
            expect(source).not.toContain(
                "import ItsRichTextEditor from '@/components/ItsRichTextEditor.vue'",
            )
        }
    })

    it('lazy loads timetable route branches and print UI', () => {
        const legacySource = readSource(
            'resources/js/pages/admin/studentsTimetables/timetable/Timetable.vue',
        )
        const v2Source = readSource(
            'resources/js/pages/admin/studentsTimetables/timetableV2/TimetableV2.vue',
        )

        expect(legacySource).toContain(
            "const Overview = defineAsyncComponent(() => import('../overview/Overview.vue'))",
        )
        expect(v2Source).toContain(
            "const TimetablePrintDialog = defineAsyncComponent(() => import('./TimetablePrintDialog.vue'))",
        )
        expect(v2Source).toContain('<TimetablePrintDialog\n            v-if="printDialogVisible"')
    })
})
