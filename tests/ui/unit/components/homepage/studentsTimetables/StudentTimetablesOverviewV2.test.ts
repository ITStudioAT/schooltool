import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const overviewV2Path = resolve(
    process.cwd(),
    'resources/js/pages/homepage/studentsTimetables/overviewV2/OverviewV2.vue',
)
const homepageRoutesPath = resolve(process.cwd(), 'resources/routes/homepage.js')

describe('Student timetables overview V2 preparation', () => {
    it('registers the user-side overview V2 route', () => {
        const source = readFileSync(homepageRoutesPath, 'utf8')

        expect(source).toContain("import StudentTimetablesOverviewV2 from '@/pages/homepage/studentsTimetables/overviewV2/OverviewV2.vue'")
        expect(source).toContain("{ path: '/students-timetables/overview-v2', component: StudentTimetablesOverviewV2 }")
    })

    it('keeps the V2 page behind the existing student timetable authentication flow', () => {
        const source = readFileSync(overviewV2Path, 'utf8')

        expect(source).toContain('<StudentTimetablesNavigationDrawer v-model="showDrawer" current-route="overview-v2" />')
        expect(source).toContain('this.studentTimetablesStore = useStudentTimetablesUserStore()')
        expect(source).toContain('const isAuthenticated = await this.studentTimetablesStore.getCurrentUser()')
        expect(source).toContain("this.$router.push('/homepage/students-timetables')")
        expect(source).toContain('await this.studentTimetablesStore.loadOverview()')
        expect(source).toContain("$router.push('/students-timetables/overview')")
    })
})
