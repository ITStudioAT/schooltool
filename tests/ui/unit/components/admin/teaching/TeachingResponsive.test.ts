import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import Teaching from '@/pages/admin/teaching/Teaching.vue'

function componentSource(relativePath: string) {
    return readFileSync(resolve(relativePath), 'utf8')
}

describe('Teaching handset responsiveness', () => {
    it('keeps the active teaching section synchronized with route changes', () => {
        const watcher = (Teaching as any).watch['$route.params.section']
        const context = {
            main_action: 'overview',
            normalizedSection: (Teaching as any).methods.normalizedSection,
            syncSection: (Teaching as any).methods.syncSection,
        }

        watcher.call(context, 'curricula')
        expect(context.main_action).toBe('curricula')

        watcher.call(context, 'unknown-section')
        expect(context.main_action).toBe('overview')

        watcher.call(context, undefined)
        expect(context.main_action).toBe('overview')
    })

    it('uses full-width handset navigation and touch-sized controls', () => {
        const teaching = componentSource('resources/js/pages/admin/teaching/Teaching.vue')
        const overview = componentSource('resources/js/pages/admin/teaching/overview/Overview.vue')

        expect(teaching).toContain("'$route.params.section'(section)")
        expect(teaching).toContain('@media (max-width: 480px)')
        expect(teaching).toContain('grid-template-columns: 1fr;')
        expect(teaching).toContain('min-height: 44px !important;')
        expect(overview).toContain('min-height: 48px !important;')
        expect(overview).toContain('.curriculum-sync-entry-actions :deep(.v-btn)')
    })

    it('stacks school-hour actions on phones', () => {
        const schoolHours = componentSource('resources/js/pages/admin/teaching/admin/schoolhours/SchoolHours.vue')

        expect(schoolHours).toContain('class="school-hour-actions d-flex align-center ga-1"')
        expect(schoolHours).toContain('.school-hour-row {\n        align-items: stretch;\n        flex-direction: column;')
    })

    it('keeps backup and curriculum actions reachable on narrow screens', () => {
        const dataBackup = componentSource('resources/js/pages/admin/teaching/backup/DataBackup.vue')
        const curriculaOverview = componentSource('resources/js/pages/admin/teaching/curricula/CurriculaOverview.vue')
        const curriculumDetail = componentSource('resources/js/pages/admin/teaching/curricula/CurriculumDetail.vue')

        expect(dataBackup).toContain('class="teaching-data-backup-dialog-actions px-4 pb-4"')
        expect(dataBackup).toContain('grid-template-columns: 1fr;')
        expect(curriculaOverview).toContain("'append append' !important;")
        expect(curriculaOverview).toContain('.curricula-overview__item :deep(.v-list-item__append)')
        expect(curriculumDetail).toContain('.curriculum-detail__fullscreen-preview-header {\n        align-items: stretch;\n        flex-direction: column;')
        expect(curriculumDetail).toContain('height: 100%;')
    })

    it('stacks course-student bulk and candidate actions on phones', () => {
        const courseStudents = componentSource('resources/js/pages/admin/teaching/overview/components/CourseStudents.vue')
        const myCourses = componentSource('resources/js/pages/admin/teaching/overview/components/MyCourses.vue')

        expect(courseStudents).toContain('class="bulk-entry-footer d-flex align-center justify-space-between mt-2"')
        expect(courseStudents).toContain('.bulk-entry-selection-actions')
        expect(courseStudents).toContain('grid-template-columns: repeat(2, minmax(0, 1fr));')
        expect(myCourses).toContain('class="course-student-candidate d-flex align-center ga-2 w-100"')
        expect(myCourses).toContain('.course-student-candidate__action {\n        margin-left: 0;\n        min-height: 44px;\n        width: 100%;')
    })
})
