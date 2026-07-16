import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

function readSource(path: string) {
    return readFileSync(resolve(path), 'utf8')
}

function templateOf(source: string) {
    return source.split('<script>')[0]
}

describe('content-only curricula', () => {
    it('shows themes and units without date or month assignment controls', () => {
        const detailTemplate = templateOf(readSource('resources/js/pages/admin/teaching/curricula/CurriculumDetail.vue'))

        expect(detailTemplate).toContain('@click="openTopicForm()"')
        expect(detailTemplate).toContain('@click="openUnitForm(topic.id)"')
        expect(detailTemplate).not.toContain('semester_count')
        expect(detailTemplate).not.toContain('free_weeks')
        expect(detailTemplate).not.toContain('assignment_type')
        expect(detailTemplate).not.toContain('month_key')
        expect(detailTemplate).not.toContain('week_key')
        expect(detailTemplate).not.toContain('curriculum-detail__calendar')
    })

    it('removes curriculum scheduling from navigation, printing, and course dates', () => {
        const curriculaSource = readSource('resources/js/pages/admin/teaching/curricula/Curricula.vue')
        const printTemplate = templateOf(readSource('resources/js/pages/admin/teaching/curricula/CurriculaPrint.vue'))
        const overviewTemplate = templateOf(readSource('resources/js/pages/admin/teaching/overview/Overview.vue'))
        const courseDatesTemplate = templateOf(readSource('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'))

        expect(curriculaSource).not.toContain("key: 'settings'")
        expect(printTemplate).not.toContain('assignmentLabel')
        expect(printTemplate).not.toContain('topicDateRange')
        expect(overviewTemplate).not.toContain('Termine synchronisieren')
        expect(courseDatesTemplate).not.toContain('Curriculum-Vorschläge')
        expect(courseDatesTemplate).not.toContain('Aus Curriculum übernehmen')
    })

    it('removes the curriculum settings component and free-week store API', () => {
        const settingsPath = resolve('resources/js/pages/admin/teaching/curricula/CurriculaSettings.vue')
        const storeSource = readSource('resources/js/stores/admin/teaching/CurriculumStore.js')

        expect(existsSync(settingsPath)).toBe(false)
        expect(storeSource).not.toContain('free_weeks_template')
        expect(storeSource).not.toContain('FreeWeeksTemplate')
        expect(storeSource).not.toContain('free-weeks-template')
    })
})
