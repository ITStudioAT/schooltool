import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import TimetablePrintDialog from '@/pages/admin/studentsTimetables/timetableV2/TimetablePrintDialog.vue'

describe('TimetablePrintDialog', () => {
    it('declares the complete controlled dialog contract', () => {
        expect(Object.keys(TimetablePrintDialog.props)).toEqual([
            'modelValue',
            'singleWeeks',
            'courseList',
            'courseOverview',
            'canPrint',
            'exporting',
        ])
        expect(TimetablePrintDialog.emits).toEqual([
            'update:modelValue',
            'update:singleWeeks',
            'update:courseList',
            'update:courseOverview',
            'print',
        ])
    })

    it('keeps all print choices and actions in the extracted component', () => {
        const source = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetableV2/TimetablePrintDialog.vue',
            'utf8',
        )

        expect(source).toContain('label="Einzelne Wochen drucken"')
        expect(source).toContain('label="Modulliste"')
        expect(source).toContain('label="Modulübersicht"')
        expect(source).toContain(":disabled=\"!canPrint\"")
        expect(source).toContain("@click=\"$emit('print')\"")
    })
})
