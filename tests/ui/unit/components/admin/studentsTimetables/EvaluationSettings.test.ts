import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import EvaluationSettings from '@/pages/admin/studentsTimetables/evaluationSettings/EvaluationSettings.vue'

describe('Students timetable evaluation settings', () => {
    it('contains the API wiring and priority controls', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/evaluationSettings/EvaluationSettings.vue',
            'utf8',
        )
        const timetableSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetable/Timetable.vue',
            'utf8',
        )

        expect(componentSource).toContain("axios.get('/api/admin/students-timetables/evaluation-settings')")
        expect(componentSource).toContain("axios.put('/api/admin/students-timetables/evaluation-settings'")
        expect(componentSource).toContain('Einzeltermine werden nicht berücksichtigt.')
        expect(componentSource).toContain('v-model="criterion.enabled"')
        expect(componentSource).toContain('v-model="criterion.option"')
        expect(componentSource).toContain('mdi-arrow-up')
        expect(componentSource).toContain('mdi-arrow-down')
        expect(componentSource).toContain('moveCriterion(index, -1)')
        expect(componentSource).toContain('moveCriterion(index, 1)')
        expect(componentSource).toContain('storageCriteria(criteria)')
        expect(timetableSource).toContain('components: { EvaluationSettings, FileUpload, Overview, RobotTimetable }')
    })

    it('normalizes priorities and builds the storage payload', () => {
        const methods = (EvaluationSettings as any).methods
        const criteria = [
            {
                key: 'free_days',
                enabled: true,
                priority: 2,
                options: null,
            },
            {
                key: 'few_gaps',
                enabled: false,
                priority: 1,
                option: 'ignored',
                options: [],
            },
        ]
        const ctx = {
            criteria,
            cloneCriteria: methods.cloneCriteria,
            normalizePriorities: methods.normalizePriorities,
            storageCriteria: methods.storageCriteria,
        }

        expect(methods.normalizedCriteria.call(ctx, criteria).map((criterion: Record<string, unknown>) => criterion.key))
            .toEqual(['few_gaps', 'free_days'])

        methods.moveCriterion.call(ctx, 0, 1)

        expect(ctx.criteria.map((criterion: Record<string, unknown>) => criterion.priority)).toEqual([1, 2])
        expect(methods.storageCriteria(ctx.criteria)).toEqual([
            {
                key: 'few_gaps',
                enabled: false,
                priority: 1,
                option: 'ignored',
            },
            {
                key: 'free_days',
                enabled: true,
                priority: 2,
                option: null,
            },
        ])
    })
})
