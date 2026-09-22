import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('homepage product labels', () => {
    it('does not advertise the removed tutoring product', () => {
        for (const page of ['Index.vue', 'Index90.vue', 'Products.vue']) {
            const source = readFileSync(resolve(process.cwd(), 'resources/js/pages/homepage/index', page), 'utf8')

            expect(source).not.toMatch(/Nachhilfe|Schüler helfen Schülern|tutoring/iu)
        }
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
