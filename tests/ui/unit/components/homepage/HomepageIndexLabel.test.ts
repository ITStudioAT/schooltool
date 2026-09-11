import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('homepage product labels', () => {
    it('uses the configured Schüler helfen Schülern product label consistently', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/homepage/index/Index.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("return 'Schüler helfen Schülern'")
        expect(source).not.toContain('Schüler helfen Schülern (Testversion)')
        expect(source).toContain('<h3 class="card-title">{{ tutoringDisplayName }}</h3>')
        expect(source).toContain("Nachhilfetool: this.tutoringDisplayName")
    })

    it('uses SEPP throughout the timetable app and explains the name at entry points', () => {
        const componentPaths = [
            'resources/js/pages/homepage/index/Index.vue',
            'resources/js/pages/homepage/studentsTimetables/StudentTimetables.vue',
            'resources/js/pages/homepage/studentsTimetables/overview/Overview.vue',
            'resources/js/pages/homepage/studentsTimetables/overviewV2/OverviewV2.vue',
            'resources/js/pages/homepage/studentsTimetables/components/StudentTimetablesNavigationDrawer.vue',
            'resources/js/pages/admin/studentsTimetables/StudentsTimetables.vue',
            'resources/js/pages/admin/studentsTimetables/settings/AdminUsers.vue',
            'resources/js/pages/admin/settings/components/StudentsTimetablesTeachers.vue',
            'resources/js/pages/admin/settings/components/ModuleStatusesCard.vue',
        ]

        for (const componentPath of componentPaths) {
            const source = readFileSync(resolve(process.cwd(), componentPath), 'utf8')

            expect(source, componentPath).toContain('SEPP')
            expect(source, componentPath).not.toMatch(/Schülerstundenpl[äa]/u)
        }

        for (const componentPath of componentPaths.slice(0, 2)) {
            expect(readFileSync(resolve(process.cwd(), componentPath), 'utf8')).toContain('Stundenplanerstellungs- und -planungsprogramm')
        }
    })
})
