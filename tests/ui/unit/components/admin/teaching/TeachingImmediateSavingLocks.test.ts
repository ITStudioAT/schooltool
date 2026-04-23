import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'
import MyCourses from '@/pages/admin/teaching/overview/components/MyCourses.vue'
import CourseInfos from '@/pages/admin/teaching/overview/components/CourseInfos.vue'
import CourseStudent from '@/pages/admin/teaching/overview/components/CourseStudent.vue'
import CourseDates from '@/pages/admin/teaching/overview/components/CourseDates.vue'
import Settings from '@/pages/admin/teaching/settings/Settings.vue'
import Behaviour from '@/pages/admin/teaching/settings/components/Behaviour.vue'
import Notifications from '@/pages/admin/teaching/settings/components/Notifications.vue'
import WorksAndGrades from '@/pages/admin/teaching/settings/components/WorksAndGrades.vue'
import Grading from '@/pages/admin/teaching/settings/components/Grading.vue'
import CategoryEvaluation from '@/pages/admin/teaching/settings/components/CategoryEvaluation.vue'
import MyHolidays from '@/pages/admin/teaching/settings/components/MyHolidays.vue'
import Holidays from '@/pages/admin/teaching/admin/holidays/Holidays.vue'
import SchoolHours from '@/pages/admin/teaching/admin/schoolhours/SchoolHours.vue'

describe('Teaching immediate saving locks', () => {
    it('keeps the immediate-lock pattern in the touched teaching components', () => {
        expect(MyCourses).toBeTruthy()
        expect(CourseInfos).toBeTruthy()
        expect(CourseStudent).toBeTruthy()
        expect(CourseDates).toBeTruthy()
        expect(Settings).toBeTruthy()
        expect(Behaviour).toBeTruthy()
        expect(Notifications).toBeTruthy()
        expect(WorksAndGrades).toBeTruthy()
        expect(Grading).toBeTruthy()
        expect(CategoryEvaluation).toBeTruthy()
        expect(MyHolidays).toBeTruthy()
        expect(Holidays).toBeTruthy()
        expect(SchoolHours).toBeTruthy()

        const expectations = [
            ['resources/js/pages/admin/teaching/overview/components/MyCourses.vue', 'saving_course_action'],
            ['resources/js/pages/admin/teaching/overview/components/CourseInfos.vue', 'saving_info_action'],
            ['resources/js/pages/admin/teaching/overview/components/CourseStudent.vue', 'saving_action_key'],
            ['resources/js/pages/admin/teaching/overview/components/CourseDates.vue', 'pending_date_mutation_action'],
            ['resources/js/pages/admin/teaching/settings/Settings.vue', 'schema_settings_saving_action'],
            ['resources/js/pages/admin/teaching/settings/components/Behaviour.vue', 'behaviour_save_action'],
            ['resources/js/pages/admin/teaching/settings/components/Notifications.vue', 'notification_save_action'],
            ['resources/js/pages/admin/teaching/settings/components/WorksAndGrades.vue', 'is_saving_settings'],
            ['resources/js/pages/admin/teaching/settings/components/Grading.vue', 'is_saving_grading'],
            ['resources/js/pages/admin/teaching/settings/components/CategoryEvaluation.vue', 'category_evaluation_save_action'],
            ['resources/js/pages/admin/teaching/settings/components/MyHolidays.vue', 'my_holidays_save_action'],
            ['resources/js/pages/admin/teaching/admin/holidays/Holidays.vue', 'holidays_save_action'],
            ['resources/js/pages/admin/teaching/admin/schoolhours/SchoolHours.vue', 'school_hours_save_action'],
        ] as const

        expectations.forEach(([relativePath, actionFlag]) => {
            const source = readFileSync(resolve(process.cwd(), relativePath), 'utf8')

            expect(source).toContain(actionFlag)
            expect(source).toContain('await this.$nextTick()')
        })
    })
})
