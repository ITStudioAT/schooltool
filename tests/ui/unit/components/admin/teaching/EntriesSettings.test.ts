import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import Entries from '@/pages/admin/teaching/settings/components/Entries.vue'

function entryFixture(overrides = {}) {
    return {
        id: 1,
        teaching_entry_area_id: 10,
        teaching_entry_grading_part_id: null,
        short_name: 'M',
        name: 'Mitarbeit',
        description: null,
        category: 'Benotung',
        has_properties: true,
        properties_mode: 'fixed',
        fixed_properties: ['+', '-'],
        has_notifications: false,
        notification_recipients: [],
        has_table_marking: false,
        table_marking_color: null,
        ...overrides,
    }
}

describe('Teaching entries settings', () => {
    it.each(['points', 'weighted'])('inherits individual point weighting %s from the part and saves only applicable entry settings', async (mode) => {
        const methods = (Entries as any).methods
        const computed = (Entries as any).computed
        const part = { id: 20, allowed_entry_types: 'points', points_assessment_mode: 'individual', individual_points_weighting_mode: mode }
        const entry = entryFixture({ properties_mode: 'points', maximum_points: 10, points_grade_thresholds: { 1: 9, 2: 8, 3: 7, 4: 5 },
            teaching_entry_grading_part_id: 20, grading_part_assessment_mode: 'other', grading_part_other_assessment_mode: 'points', grading_part_weight: null })
        const ctx: any = { gradingParts: [part], calculationEntries: [entry], calculationDialogHasPartAssessment: true,
            replaceGradingEntry: vi.fn(), closeCalculationDialog: methods.closeCalculationDialog, notifyError: vi.fn() }
        const put = vi.fn().mockResolvedValue({ data: { data: entry } })
        vi.stubGlobal('axios', { put })
        try {
            methods.openCalculationDialog.call(ctx, entry)
            expect(computed.calculationDialogHasPartAssessment.call(ctx)).toBe(true)
            expect(computed.calculationDialogIndividualPointWeighting.call(ctx)).toBe(mode)
            expect(computed.calculationDialogUsesPartWeight.call(ctx)).toBe(mode === 'weighted')
            if (mode === 'weighted') {
                expect(ctx.calculationDialogPartWeight).toBeNull()
                expect(methods.entryCalculationIssue.call(ctx, entry)).toContain('positive ganze Zahl')
                expect(methods.gradingPartCalculationIssue.call(ctx, { ...part, entries: [entry] })).toContain('positive ganze Zahl')
                await methods.saveCalculationDialog.call(ctx)
                expect(put).not.toHaveBeenCalled()
                ctx.calculationDialogPartWeight = '3'
                expect(methods.entryCalculationIssue.call(ctx, { ...entry, grading_part_weight: 3 })).toBe('')
                expect(methods.entryWeightLabel.call(ctx, { ...entry, grading_part_weight: 3 }, 1)).toBe('3')
            } else {
                expect(methods.entryCalculationIssue.call(ctx, entry)).toBe('')
                expect(methods.entryWeightLabel.call(ctx, { ...entry, grading_part_weight: 3 }, 1)).toBe('')
            }
            await methods.saveCalculationDialog.call(ctx)
            const payload = put.mock.calls.at(-1)[1]
            expect(payload).not.toHaveProperty('grading_part_assessment_mode')
            expect(payload).not.toHaveProperty('grading_part_other_assessment_mode')
            if (mode === 'weighted') expect(payload.grading_part_weight).toBe(3)
            else expect(payload).not.toHaveProperty('grading_part_weight')
        } finally { vi.unstubAllGlobals() }
    })

    it.each(['points', 'weighted'])('saves and restores individual point weighting %s on the part', async (mode) => {
        const methods = (Entries as any).methods
        const ctx: any = { activeAreaId: 10, gradingParts: [], gradingPartWeightValid: true,
            closeGradingPartDialog: methods.closeGradingPartDialog, notifyError: vi.fn() }
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { id: 20, ...payload } } }))
        vi.stubGlobal('axios', { put })
        try {
            methods.openEditGradingPartDialog.call(ctx, { gradingPartId: 20, name: 'Tests', allowed_entry_types: 'points', points_assessment_mode: 'individual' })
            ctx.gradingPartForm.individual_points_weighting_mode = mode
            await methods.saveGradingPart.call(ctx)
            expect(put.mock.calls.at(-1)[1].individual_points_weighting_mode).toBe(mode)
            const saved = { ...ctx.gradingParts[0], gradingPartId: 20 }
            methods.openEditGradingPartDialog.call(ctx, saved)
            expect(ctx.gradingPartForm.individual_points_weighting_mode).toBe(mode)
            ctx.gradingPartForm.individual_points_weighting_mode = mode === 'points' ? 'weighted' : 'points'
            methods.closeGradingPartDialog.call(ctx)
            methods.openEditGradingPartDialog.call(ctx, saved)
            expect(ctx.gradingPartForm.individual_points_weighting_mode).toBe(mode)
            ctx.gradingPartForm.points_assessment_mode = 'overall'
            await methods.saveGradingPart.call(ctx)
            expect(put.mock.calls.at(-1)[1]).not.toHaveProperty('individual_points_weighting_mode')
        } finally { vi.unstubAllGlobals() }
    })

    it('saves and restores the weight under Andere Beurteilung for points', async () => {
        const methods = (Entries as any).methods
        const entry = entryFixture({ properties_mode: 'points', teaching_entry_grading_part_id: 20, grading_part_assessment_mode: 'other' })
        const ctx: any = { calculationDialogHasPartAssessment: true, calculationDialogOverallPart: { id: 20 },
            replaceGradingEntry: vi.fn(), closeCalculationDialog: methods.closeCalculationDialog, notifyError: vi.fn() }
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { ...entry, ...payload } } }))
        vi.stubGlobal('axios', { put })
        try {
            methods.openCalculationDialog.call(ctx, entry)
            ctx.calculationDialogPartOtherAssessmentMode = 'weighted'
            expect((Entries as any).computed.calculationDialogUsesPartWeight.call(ctx)).toBe(true)
            for (const invalid of ['0', '-2', '1,5', '', true]) {
                ctx.calculationDialogPartWeight = invalid
                await methods.saveCalculationDialog.call(ctx)
                expect(put).not.toHaveBeenCalled()
                expect(ctx.calculationDialogErrors.grading_part_weight).toBeTruthy()
            }
            ctx.calculationDialogPartWeight = '6'
            await methods.saveCalculationDialog.call(ctx)
            expect(put).toHaveBeenCalledWith(expect.any(String), {
                grading_part_assessment_mode: 'other', grading_part_other_assessment_mode: 'weighted', grading_part_weight: 6,
            })
            const saved = ctx.replaceGradingEntry.mock.calls[0][0]
            methods.openCalculationDialog.call(ctx, saved)
            expect(ctx.calculationDialogPartOtherAssessmentMode).toBe('weighted')
            expect(ctx.calculationDialogPartWeight).toBe(6)
            expect(methods.entryWeightLabel(saved, 2)).toBe('6')
            ctx.calculationDialogPartOtherAssessmentMode = 'points'
            expect((Entries as any).computed.calculationDialogUsesPartWeight.call(ctx)).toBe(false)
            await methods.saveCalculationDialog.call(ctx)
            expect(put.mock.calls.at(-1)[1]).not.toHaveProperty('grading_part_weight')
        } finally { vi.unstubAllGlobals() }
    })

    it('uses distinct overview symbols for the saved plus-minus assessment methods', () => {
        const symbol = (Entries as any).methods.entryAssessmentSymbol
        const entry = entryFixture({ properties_mode: 'plus_minus', grading_part_assessment_mode: 'other' })
        const rounding = symbol({ ...entry, grading_part_other_assessment_mode: 'balance_rounding' })
        const adjustment = symbol({ ...entry, grading_part_other_assessment_mode: 'balance_adjustment' })
        expect(rounding.icon).toBe('mdi-arrow-up-down-bold')
        expect(rounding.label).toContain('Gesamtbeurteilung aufrunden')
        expect(adjustment.icon).toBe('mdi-plus-minus')
        expect(adjustment.label).toContain('überschüssigem Plus oder Minus')
        for (const overrides of [{ grading_part_assessment_mode: 'weighted' }, { properties_mode: 'points' }, { grading_part_other_assessment_mode: null }]) {
            expect(symbol({ ...entry, grading_part_other_assessment_mode: 'balance_adjustment', ...overrides })).toBeNull()
        }
    })

    it('saves decimal surplus adjustments and restores them after closing without saving', async () => {
        const methods = (Entries as any).methods
        const entry = entryFixture({ properties_mode: 'plus_minus', teaching_entry_grading_part_id: 20, grading_part_assessment_mode: 'other' })
        const ctx: any = { calculationDialogHasPartAssessment: true, replaceGradingEntry: vi.fn(), closeCalculationDialog: methods.closeCalculationDialog, notifyError: vi.fn() }
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { ...entry, ...payload } } }))
        vi.stubGlobal('axios', { put })
        try {
            methods.openCalculationDialog.call(ctx, entry)
            ctx.calculationDialogPartOtherAssessmentMode = 'balance_adjustment'
            ctx.calculationDialogPlusAdjustment = '0,25'
            ctx.calculationDialogMinusAdjustment = '0,5'
            await methods.saveCalculationDialog.call(ctx)
            expect(put).toHaveBeenCalledWith(expect.any(String), {
                grading_part_assessment_mode: 'other', grading_part_other_assessment_mode: 'balance_adjustment',
                grading_part_plus_adjustment: 0.25, grading_part_minus_adjustment: 0.5,
            })
            const saved = ctx.replaceGradingEntry.mock.calls[0][0]
            methods.openCalculationDialog.call(ctx, saved)
            expect(ctx.calculationDialogPlusAdjustment).toBe(0.25)
            expect(ctx.calculationDialogMinusAdjustment).toBe(0.5)
            ctx.calculationDialogPlusAdjustment = '9,5'
            methods.closeCalculationDialog.call(ctx)
            methods.openCalculationDialog.call(ctx, saved)
            expect(ctx.calculationDialogPlusAdjustment).toBe(0.25)
            ctx.calculationDialogPartOtherAssessmentMode = 'balance_rounding'
            await methods.saveCalculationDialog.call(ctx)
            expect(put.mock.calls.at(-1)[1]).not.toHaveProperty('grading_part_plus_adjustment')
            expect(put.mock.calls.at(-1)[1]).not.toHaveProperty('grading_part_minus_adjustment')
        } finally { vi.unstubAllGlobals() }
    })

    it.each([null, '', '-0,1', 'invalid', Infinity, true])('blocks invalid adjustment %s in either field', async (invalid) => {
        const methods = (Entries as any).methods
        const entry = entryFixture({ properties_mode: 'plus_minus', teaching_entry_grading_part_id: 20,
            grading_part_assessment_mode: 'other', grading_part_other_assessment_mode: 'balance_adjustment' })
        const ctx: any = { calculationDialogHasPartAssessment: true }
        const put = vi.fn()
        vi.stubGlobal('axios', { put })
        try {
            for (const [plus, minus] of [[invalid, 0], [0, invalid]]) {
                methods.openCalculationDialog.call(ctx, entry)
                ctx.calculationDialogPlusAdjustment = plus
                ctx.calculationDialogMinusAdjustment = minus
                await methods.saveCalculationDialog.call(ctx)
                expect(ctx.calculationDialogErrors.grading_part_plus_adjustment).toBeTruthy()
                expect(put).not.toHaveBeenCalled()
            }
        } finally { vi.unstubAllGlobals() }
    })

    it('flags incomplete adjustments while accepting zero and decimal values', () => {
        const entry = entryFixture({ properties_mode: 'plus_minus', teaching_entry_grading_part_id: 20,
            grading_part_assessment_mode: 'other', grading_part_other_assessment_mode: 'balance_adjustment',
            grading_part_plus_adjustment: 0, grading_part_minus_adjustment: 0.25 })
        const ctx = { gradingParts: [], calculationEntries: [entry] }
        expect((Entries as any).methods.entryCalculationIssue.call(ctx, entry)).toBe('')
        entry.grading_part_minus_adjustment = null
        expect((Entries as any).methods.entryCalculationIssue.call(ctx, entry)).toContain('Kommazahlen')
    })

    it('saves the combined plus-minus rounding option and preserves it across reopening', async () => {
        const methods = (Entries as any).methods
        const entry = entryFixture({ properties_mode: 'plus_minus', teaching_entry_grading_part_id: 20, grading_part_assessment_mode: 'other' })
        const ctx: any = { calculationDialogHasPartAssessment: true, replaceGradingEntry: vi.fn(), closeCalculationDialog: methods.closeCalculationDialog, notifyError: vi.fn() }
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { ...entry, ...payload } } }))
        vi.stubGlobal('axios', { put })
        try {
            methods.openCalculationDialog.call(ctx, entry)
            ctx.calculationDialogPartOtherAssessmentMode = 'balance_rounding'
            await methods.saveCalculationDialog.call(ctx)
            expect(put).toHaveBeenCalledWith(expect.any(String), { grading_part_assessment_mode: 'other', grading_part_other_assessment_mode: 'balance_rounding' })
            const saved = ctx.replaceGradingEntry.mock.calls[0][0]
            methods.openCalculationDialog.call(ctx, saved)
            expect(ctx.calculationDialogPartOtherAssessmentMode).toBe('balance_rounding')
            ctx.calculationDialogPartOtherAssessmentMode = null
            methods.closeCalculationDialog.call(ctx)
            methods.openCalculationDialog.call(ctx, saved)
            expect(ctx.calculationDialogPartOtherAssessmentMode).toBe('balance_rounding')
            methods.openCalculationDialog.call(ctx, { ...saved, properties_mode: 'points' })
            expect(ctx.calculationDialogPartOtherAssessmentMode).toBeNull()
        } finally { vi.unstubAllGlobals() }
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')
        expect(source).toContain('<v-btn-toggle v-if="calculationDialogPartAssessmentMode === \'other\' && calculationDialogEntry?.properties_mode === \'plus_minus\'"')
        expect(source).toContain('Mehr Plus als Minus: Gesamtbeurteilung aufrunden')
        expect(source).toContain('Mehr Minus als Plus: Gesamtbeurteilung abrunden')
    })

    it('hides Nach Punkten entirely for types without the Punkte property', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')
        expect(source).toContain('<v-btn-toggle v-if="!calculationDialogIndividualPointWeighting && calculationDialogPartAssessmentMode === \'other\' && calculationDialogEntry?.properties_mode === \'points\'"')
        expect(source).not.toContain('„Nach Punkten“ ist nur für Eintragstypen')
    })

    it.each(['plus_minus', 'fixed'])('does not restore or submit Nach Punkten for non-point mode %s', async (mode) => {
        const methods = (Entries as any).methods
        const entry = entryFixture({ properties_mode: mode, fixed_properties: ['1', '2', '3', '4', '5'], teaching_entry_grading_part_id: 20,
            grading_part_assessment_mode: 'other', grading_part_other_assessment_mode: 'points' })
        const ctx: any = { calculationDialogHasPartAssessment: true, replaceGradingEntry: vi.fn(), closeCalculationDialog: methods.closeCalculationDialog, notifyError: vi.fn() }
        const put = vi.fn().mockResolvedValue({ data: { data: entry } })
        vi.stubGlobal('axios', { put })
        try {
            methods.openCalculationDialog.call(ctx, entry)
            expect(ctx.calculationDialogPartOtherAssessmentMode).toBeNull()
            ctx.calculationDialogPartOtherAssessmentMode = 'points'
            await methods.saveCalculationDialog.call(ctx)
            expect(put).toHaveBeenCalledWith(expect.any(String), { grading_part_assessment_mode: 'other' })
        } finally { vi.unstubAllGlobals() }
    })

    it('saves and restores Nach Punkten only for Andere Beurteilung', async () => {
        const methods = (Entries as any).methods
        const entry = entryFixture({ properties_mode: 'points', teaching_entry_grading_part_id: 20 })
        const ctx: any = { calculationDialogHasPartAssessment: true, calculationDialogOverallPart: { id: 20 }, replaceGradingEntry: vi.fn(), closeCalculationDialog: methods.closeCalculationDialog, notifyError: vi.fn() }
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { ...entry, ...payload } } }))
        vi.stubGlobal('axios', { put })
        try {
            methods.openCalculationDialog.call(ctx, entry)
            expect(ctx.calculationDialogPartOtherAssessmentMode).toBeNull()
            ctx.calculationDialogPartAssessmentMode = 'other'
            ctx.calculationDialogPartOtherAssessmentMode = 'points'
            await methods.saveCalculationDialog.call(ctx)
            expect(put).toHaveBeenCalledWith(expect.any(String), { grading_part_assessment_mode: 'other', grading_part_other_assessment_mode: 'points' })
            const saved = ctx.replaceGradingEntry.mock.calls[0][0]
            methods.openCalculationDialog.call(ctx, saved)
            expect(ctx.calculationDialogPartOtherAssessmentMode).toBe('points')
            ctx.calculationDialogPartOtherAssessmentMode = null
            methods.closeCalculationDialog.call(ctx)
            methods.openCalculationDialog.call(ctx, saved)
            expect(ctx.calculationDialogPartOtherAssessmentMode).toBe('points')
            ctx.calculationDialogPartAssessmentMode = 'weighted'
            await methods.saveCalculationDialog.call(ctx)
            expect(put.mock.calls.at(-1)[1]).not.toHaveProperty('grading_part_other_assessment_mode')
        } finally { vi.unstubAllGlobals() }
    })

    it('places the internal weight before entry details and uses the short code only as a fallback', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')
        const card = source.slice(source.indexOf('<ul v-if="area.entries.length"'), source.indexOf('</ul>', source.indexOf('<ul v-if="area.entries.length"')))
        expect(card.indexOf('class="calculation-entry-weight"')).toBeLessThan(card.indexOf('class="calculation-entry-details"'))
        expect(card).toContain('<v-chip v-else class="calculation-entry-code"')
        expect(card.match(/class="calculation-entry-weight"/g)).toHaveLength(1)
    })

    it.each([
        [{}, 3, '1'], [{ grading_part_weight: 6, grading_part_assessment_mode: 'weighted' }, 3, '6'],
        [{ grading_part_weight: 4 }, 2, '4'], [{ grading_part_weight: 6, grading_part_assessment_mode: 'other' }, 3, ''],
        [{ grading_part_weight: 6 }, 1, ''],
    ])('shows the saved internal weight %j for a part with %s types', (entry, count, label) => {
        expect((Entries as any).methods.entryWeightLabel(entry, count)).toBe(label)
    })

    it('offers part assessment only when multiple entry types belong to the same part', () => {
        const entry = entryFixture({ teaching_entry_grading_part_id: 20 })
        const ctx: any = { calculationDialogEntry: entry, calculationEntries: [entry] }
        const enabled = (Entries as any).computed.calculationDialogHasPartAssessment
        expect(enabled.call(ctx)).toBe(false)
        ctx.calculationEntries.push(entryFixture({ id: 2, teaching_entry_grading_part_id: 30 }))
        expect(enabled.call(ctx)).toBe(false)
        ctx.calculationEntries.push(entryFixture({ id: 3, teaching_entry_grading_part_id: 20 }))
        expect(enabled.call(ctx)).toBe(true)
        ctx.calculationDialogEntry = entryFixture()
        expect(enabled.call(ctx)).toBe(false)
    })

    it('marks previously saved fractional internal weights as requiring correction', () => {
        const entry = entryFixture({ properties_mode: 'plus_minus', teaching_entry_grading_part_id: 20, grading_part_weight: 2.5 })
        const ctx = { gradingParts: [], calculationEntries: [entry, entryFixture({ id: 2, teaching_entry_grading_part_id: 20 })] }
        expect((Entries as any).methods.entryCalculationIssue.call(ctx, entry)).toContain('positive ganze Zahl')
        entry.grading_part_weight = 3
        expect((Entries as any).methods.entryCalculationIssue.call(ctx, entry)).toBe('')
    })

    it.each(['fixed', 'plus_minus', 'points'])('saves internal weighting for %s without adding unrelated calculation settings', async (propertyMode) => {
        const methods = (Entries as any).methods
        const entry = entryFixture({ properties_mode: propertyMode, fixed_properties: ['1', '2', '3', '4', '5'], teaching_entry_grading_part_id: 20 })
        const ctx: any = { calculationDialogHasPartAssessment: true, calculationDialogOverallPart: propertyMode === 'points' ? { id: 20 } : null,
            replaceGradingEntry: vi.fn(), closeCalculationDialog: methods.closeCalculationDialog, notifyError: vi.fn() }
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { ...entry, ...payload } } }))
        vi.stubGlobal('axios', { put })
        try {
            methods.openCalculationDialog.call(ctx, entry)
            expect(ctx.calculationDialogPartWeight).toBe(1)
            expect(ctx.calculationDialogPartAssessmentMode).toBe('weighted')
            ctx.calculationDialogPartWeight = '3'
            await methods.saveCalculationDialog.call(ctx)
            expect(put).toHaveBeenCalledWith(expect.any(String), { grading_part_assessment_mode: 'weighted', grading_part_weight: 3 })
            const saved = ctx.replaceGradingEntry.mock.calls[0][0]
            methods.openCalculationDialog.call(ctx, saved)
            expect(ctx.calculationDialogPartWeight).toBe(3)
            ctx.calculationDialogPartWeight = 10
            methods.closeCalculationDialog.call(ctx)
            methods.openCalculationDialog.call(ctx, saved)
            expect(ctx.calculationDialogPartWeight).toBe(3)
            ctx.calculationDialogPartAssessmentMode = 'other'
            await methods.saveCalculationDialog.call(ctx)
            expect(put).toHaveBeenLastCalledWith(expect.any(String), { grading_part_assessment_mode: 'other' })
        } finally { vi.unstubAllGlobals() }
    })

    it.each(['', '0', '-1', '2,5', '2.5', '1,0001', 'Infinity'])('blocks invalid internal weighting %s', async (weight) => {
        const ctx: any = { calculationDialogHasPartAssessment: true, calculationDialogPartAssessmentMode: 'weighted', calculationDialogPartWeight: weight }
        expect((Entries as any).computed.calculationDialogPartWeightError.call(ctx)).toContain('positive ganze Zahl')
        const put = vi.fn()
        vi.stubGlobal('axios', { put })
        try {
            await (Entries as any).methods.saveCalculationDialog.call(ctx)
            expect(put).not.toHaveBeenCalled()
            expect(ctx.calculationDialogErrors.grading_part_weight[0]).toContain('positive ganze Zahl')
        } finally { vi.unstubAllGlobals() }
    })

    it.each([
        [{ properties_mode: 'plus', allows_maximum_plus: false }, true],
        [{ properties_mode: 'plus', allows_maximum_plus: true, maximum_plus_grading_mode: 'standard_percentage' }, false],
        [{ properties_mode: 'plus', allows_maximum_plus: false, maximum_plus_grade_thresholds: { 1: 8, 2: 6, 3: 4, 4: 2 } }, false],
        [{ properties_mode: 'plus', maximum_plus_grade_thresholds: { 1: 8.5, 2: 6, 3: 4, 4: 2 } }, true],
        [{ properties_mode: 'plus_minus' }, false],
        [{ properties_mode: 'fixed', fixed_properties: ['1', '2', '3', '4', '5'] }, false],
        [{ properties_mode: 'points', maximum_points: 20 }, true],
        [{ properties_mode: 'points', maximum_points: 20, points_grade_thresholds: { 1: 18, 2: 15, 3: 12, 4: 10 } }, false],
        [{ properties_mode: 'points', maximum_points: 10, points_grade_thresholds: { 1: 18, 2: 15, 3: 12, 4: 10 } }, true],
    ])('flags missing calculation configuration for %j: %s', (settings, incomplete) => {
        expect(Boolean((Entries as any).methods.entryCalculationIssue.call({ gradingParts: [], calculationEntries: [] }, entryFixture(settings)))).toBe(incomplete)
    })

    it('checks free value mappings and the active thresholds while leaving special codes optional', () => {
        const entry = entryFixture({ properties_mode: 'free', fixed_properties: ['erledigt', 'nicht erledigt'], free_grading_mode: 'points',
            enabled_special_properties: ['NA', 'VL', 'F'], property_evaluations: [{ property: 'erledigt', evaluation: 'positive' }],
            free_points_grade_thresholds: { 1: 2.5, 2: 1, 3: 0, 4: -1 } })
        const issue = (Entries as any).methods.entryCalculationIssue
        const ctx = { gradingParts: [], calculationEntries: [entry] }
        expect(issue.call(ctx, entry)).toContain('Wertezuordnung')
        entry.property_evaluations.push({ property: 'nicht erledigt', evaluation: 'ignored' })
        expect(issue.call(ctx, entry)).toBe('')
        entry.free_grading_mode = 'deficit_points'
        expect(issue.call(ctx, entry)).toContain('alle vier')
        Object.assign(entry, { free_deficit_grade_thresholds: { 1: 0, 2: 0.5, 3: 1, 4: 2 } })
        expect(issue.call(ctx, entry)).toBe('')
    })

    it('uses overall thresholds for child warnings and clears warnings after the shared configuration is complete', () => {
        const entry = entryFixture({ properties_mode: 'points', maximum_points: 20, teaching_entry_grading_part_id: 20 })
        const part: any = { id: 20, name: 'Gesamt', allowed_entry_types: 'points', points_assessment_mode: 'overall' }
        const ctx = { gradingParts: [part], calculationEntries: [entry] }
        const methods = (Entries as any).methods
        expect(methods.entryCalculationIssue.call(ctx, entry)).toContain('alle vier')
        expect(methods.gradingPartCalculationIssue.call(ctx, { ...part, gradingPartId: 20, entries: [entry] })).toContain('alle vier')
        part.overall_points_grade_thresholds = { 1: 18, 2: 15, 3: 12, 4: 10 }
        expect(methods.entryCalculationIssue.call(ctx, entry)).toBe('')
        expect(methods.gradingPartCalculationIssue.call(ctx, { ...part, gradingPartId: 20, entries: [entry] })).toBe('')
        entry.maximum_points = 0
        expect(methods.entryCalculationIssue.call(ctx, entry)).toContain('maximale Punktzahl')
        expect(methods.gradingPartCalculationIssue.call(ctx, { ...part, gradingPartId: 20, entries: [entry] })).toContain('Höchstpunktzahl')
    })

    it('flags an unconfigured overall part but not an empty optional ordinary part', () => {
        const method = (Entries as any).methods.gradingPartCalculationIssue
        const ctx = { gradingParts: [], calculationEntries: [] }
        expect(method.call(ctx, { id: 20, entries: [], allowed_entry_types: 'all', is_required: false })).toBe('')
        expect(method.call(ctx, { id: 20, entries: [], allowed_entry_types: 'points', points_assessment_mode: 'overall' })).toContain('zugeordnete Punktetypen')
    })

    it('aggregates child configuration failures on the parent card', () => {
        const entry = entryFixture({ name: 'Auftrag', properties_mode: 'plus', allows_maximum_plus: false })
        const ctx = { gradingParts: [], calculationEntries: [entry] }
        expect((Entries as any).methods.gradingPartCalculationIssue.call(ctx, { id: 20, entries: [entry] })).toContain('Auftrag:')
        Object.assign(entry, { maximum_plus_grade_thresholds: { 1: 8, 2: 6, 3: 4, 4: 2 } })
        expect((Entries as any).methods.gradingPartCalculationIssue.call(ctx, { id: 20, entries: [entry] })).toBe('')
    })

    it('shows overall assessment for assigned point entries and prevents individual threshold changes', async () => {
        const computed = (Entries as any).computed
        const part = { id: 20, name: 'Gesamt', allowed_entry_types: 'points', points_assessment_mode: 'overall' }
        const entry = entryFixture({ properties_mode: 'points', teaching_entry_grading_part_id: 20, maximum_points: 15 })
        const ctx: any = { gradingParts: [part], calculationDialogEntry: entry, calculationDialogPointThresholds: { 1: 13, 2: 11, 3: 9, 4: 8 } }
        ctx.calculationDialogOverallPart = computed.calculationDialogOverallPart.call(ctx)
        expect(ctx.calculationDialogOverallPart).toBe(part)
        expect(computed.calculationDialogUsesPoints.call(ctx)).toBe(false)
        expect(computed.calculationDialogPointThresholdError.call({ ...ctx, calculationDialogUsesPoints: false })).toBe('')
        const put = vi.fn()
        vi.stubGlobal('axios', { put })
        try {
            await (Entries as any).methods.saveCalculationDialog.call(ctx)
            ;(Entries as any).methods.applyStandardPointThresholds.call(ctx)
            expect(put).not.toHaveBeenCalled()
            expect(ctx.calculationDialogPointThresholds).toEqual({ 1: 13, 2: 11, 3: 9, 4: 8 })
        } finally { vi.unstubAllGlobals() }
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')
        expect(source).toContain('<section v-if="calculationDialogOverallPart"')
        expect(source).toContain('<section v-else-if="calculationDialogUsesPoints"')
        expect(source).toContain('id="entry-overall-grading-title" class="text-h5 font-weight-bold">Gesamtbeurteilung</h3>')
    })

    it.each([
        ['points', 20, 'points', 'individual'], ['points', null, 'points', 'overall'],
        ['points', 30, 'points', 'overall'], ['points', 20, 'all', 'overall'], ['plus', 20, 'points', 'overall'],
    ])('does not suppress individual grading for entry mode %s in part %s with %s / %s', (mode, assigned, allowed, assessment) => {
        const ctx: any = { gradingParts: [{ id: 20, allowed_entry_types: allowed, points_assessment_mode: assessment }],
            calculationDialogEntry: entryFixture({ properties_mode: mode, teaching_entry_grading_part_id: assigned }) }
        ctx.calculationDialogOverallPart = (Entries as any).computed.calculationDialogOverallPart.call(ctx)
        expect(ctx.calculationDialogOverallPart).toBeNull()
        expect((Entries as any).computed.calculationDialogUsesPoints.call(ctx)).toBe(mode === 'points')
    })

    it('sums maxima only from point types assigned to the edited grading part', () => {
        const calculationEntries = [
            entryFixture({ id: 1, properties_mode: 'points', maximum_points: 12.5, teaching_entry_grading_part_id: 20 }),
            entryFixture({ id: 2, properties_mode: 'points', maximum_points: 7.5, teaching_entry_grading_part_id: 20 }),
            entryFixture({ id: 3, properties_mode: 'points', maximum_points: 100, teaching_entry_grading_part_id: 30 }),
            entryFixture({ id: 4, properties_mode: 'plus', maximum_points: 100, teaching_entry_grading_part_id: 20 }),
        ]
        const maximum = (Entries as any).computed.gradingPartOverallMaximumPoints
        expect(maximum.call({ editingGradingPartId: 20, calculationEntries })).toBe(20)
        expect(maximum.call({ editingGradingPartId: null, calculationEntries })).toBe(0)
    })

    it('generates editable overall thresholds from the total maximum and saves decimal commas', async () => {
        const methods = (Entries as any).methods
        const ctx: any = { activeAreaId: 10, gradingParts: [], gradingPartWeightValid: true,
            gradingPartOverallMaximumPoints: 20, closeGradingPartDialog: methods.closeGradingPartDialog, notifyError: vi.fn() }
        const part = { gradingPartId: 20, name: 'Tests', allowed_entry_types: 'points', points_assessment_mode: 'overall' }
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { id: 20, ...payload } } }))
        vi.stubGlobal('axios', { put })
        try {
            methods.openEditGradingPartDialog.call(ctx, part)
            methods.applyStandardOverallPointThresholds.call(ctx)
            expect(ctx.gradingPartOverallThresholds).toEqual({ 1: 18, 2: 15, 3: 13, 4: 10 })
            ctx.gradingPartOverallThresholds[1] = '18,25'
            expect((Entries as any).computed.gradingPartOverallThresholdError.call(ctx)).toBe('')
            await methods.saveGradingPart.call(ctx)
            expect(put).toHaveBeenCalledWith(expect.any(String), expect.objectContaining({
                overall_points_grade_thresholds: { 1: 18.25, 2: 15, 3: 13, 4: 10 },
            }))
            const saved = { ...ctx.gradingParts[0], gradingPartId: 20 }
            methods.openEditGradingPartDialog.call(ctx, saved)
            expect(ctx.gradingPartOverallThresholds[1]).toBe(18.25)
            ctx.gradingPartOverallThresholds[1] = 19
            methods.closeGradingPartDialog.call(ctx)
            methods.openEditGradingPartDialog.call(ctx, saved)
            expect(ctx.gradingPartOverallThresholds[1]).toBe(18.25)
            expect((Entries as any).computed.gradingPartOverallFailingGradeLabel.call(ctx)).toBe('Weniger als 10 Punkte')
        } finally { vi.unstubAllGlobals() }
    })

    it.each([
        [{ 1: 21, 2: 15, 3: 12, 4: 10 }, 'zwischen'],
        [{ 1: 20, 2: 20, 3: 12, 4: 10 }, 'kleiner'],
        [{ 1: null, 2: 15, 3: 12, 4: 10 }, 'alle vier'],
        [{ 1: 20, 2: 15, 3: 12, 4: -1 }, 'zwischen'],
    ])('rejects invalid overall grade thresholds %j', async (thresholds, message) => {
        const ctx: any = { activeAreaId: 10, gradingPartWeightValid: true, gradingPartOverallMaximumPoints: 20,
            gradingPartForm: { name: 'Tests', allowed_entry_types: 'points', points_assessment_mode: 'overall' }, gradingPartOverallThresholds: thresholds }
        ctx.gradingPartOverallThresholdError = (Entries as any).computed.gradingPartOverallThresholdError.call(ctx)
        expect(ctx.gradingPartOverallThresholdError).toContain(message)
        const put = vi.fn()
        vi.stubGlobal('axios', { put })
        try {
            await (Entries as any).methods.saveGradingPart.call(ctx)
            expect(put).not.toHaveBeenCalled()
            expect(ctx.gradingPartFormErrors.overall_points_grade_thresholds).toEqual([ctx.gradingPartOverallThresholdError])
        } finally { vi.unstubAllGlobals() }
    })

    it('allows creating an empty overall part before assigning its point types', async () => {
        const methods = (Entries as any).methods
        const ctx: any = { activeAreaId: 10, gradingParts: [], gradingPartWeightValid: true, gradingPartOverallMaximumPoints: 0,
            closeGradingPartDialog: methods.closeGradingPartDialog, notifyError: vi.fn() }
        const post = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { id: 20, ...payload } } }))
        vi.stubGlobal('axios', { post })
        try {
            methods.openCreateGradingPartDialog.call(ctx)
            Object.assign(ctx.gradingPartForm, { name: 'Tests', allowed_entry_types: 'points', points_assessment_mode: 'overall' })
            expect((Entries as any).computed.gradingPartOverallThresholdError.call(ctx)).toBe('')
            methods.applyStandardOverallPointThresholds.call(ctx)
            expect(ctx.gradingPartOverallThresholds[1]).toBeNull()
            await methods.saveGradingPart.call(ctx)
            expect(post).toHaveBeenCalledWith(expect.any(String), expect.objectContaining({ overall_points_grade_thresholds: null }))
        } finally { vi.unstubAllGlobals() }
    })

    it.each([[13.125, '13,125'], [0.5, '0,5'], ['0,', '0,'], [null, null]])('displays decimal field value %s with a comma without changing text drafts', (value, expected) => {
        expect((Entries as any).methods.gradingNumberInput(value)).toBe(expected)
    })

    it('sorts relative grading parts by descending weight before fixed percentages without mutating their source', () => {
        const gradingParts = [
            { id: 1, teaching_entry_area_id: 10, weight: 1, fixed_percentage: 30 },
            { id: 2, teaching_entry_area_id: 10, weight: 4, fixed_percentage: null },
            { id: 3, teaching_entry_area_id: 10, weight: 6 },
            { id: 4, teaching_entry_area_id: 20, weight: 20 },
            { id: 5, teaching_entry_area_id: 10, weight: 4, fixed_percentage: null },
            { id: 6, teaching_entry_area_id: 10, weight: 2, fixed_percentage: 20 },
        ]
        const ctx = { gradingParts, activeAreaId: 10, calculationEntries: [] }
        expect((Entries as any).computed.calculationAreas.call(ctx).map((part) => part.gradingPartId)).toEqual([3, 2, 5, 1, 6])
        expect(gradingParts.map((part) => part.id)).toEqual([1, 2, 3, 4, 5, 6])
        gradingParts[1].weight = 8
        expect((Entries as any).computed.calculationAreas.call(ctx).map((part) => part.gradingPartId)).toEqual([2, 3, 5, 1, 6])
    })

    it.each([[{ weight: 6 }, '6'], [{ weight: 4 }, '4'], [{ weight: 1, fixed_percentage: 30 }, '30 %'], [{ weight: 1.5 }, '1,5']])('formats the prominent weight badge %j', (part, expected) => {
        expect((Entries as any).methods.gradingPartWeightValue(part)).toBe(expected)
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')
        expect(source).toContain('class="grading-weight-badge" :aria-label="gradingPartWeightLabel(area)"')
        expect(source).toContain('icon="mdi-weight"')
    })

    it.each([null, '', '0', '-1', 'invalid', 'Infinity'])('requires a positive maximum for points: %s', async (maximum) => {
        const methods = (Entries as any).methods
        const ctx: any = { canSaveEntry: true, entryForm: entryFixture({ properties_mode: 'points', maximum_points: maximum }) }
        expect((Entries as any).computed.entryMaximumPointsError.call(ctx)).toContain('größer als 0')
        const put = vi.fn()
        vi.stubGlobal('axios', { put })
        try {
            await methods.saveEntry.call(ctx)
            expect(put).not.toHaveBeenCalled()
            expect(ctx.formErrors.maximum_points[0]).toContain('größer als 0')
        } finally { vi.unstubAllGlobals() }
    })

    it('saves and restores a decimal point maximum and clears it when switching away', async () => {
        const methods = (Entries as any).methods
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { id: 1, ...payload } } }))
        const ctx: any = { entries: [], canSaveEntry: true, normalizeShortName: methods.normalizeShortName, closeEditDialog: methods.closeEditDialog, notifyError: vi.fn() }
        vi.stubGlobal('axios', { put })
        try {
            methods.openEditDialog.call(ctx, entryFixture())
            methods.selectPropertyMode.call(ctx, 'points')
            ctx.entryForm.maximum_points = '12,5'
            expect((Entries as any).computed.entryMaximumPointsError.call(ctx)).toBe('')
            await methods.saveEntry.call(ctx)
            expect(put).toHaveBeenLastCalledWith(expect.any(String), expect.objectContaining({ properties_mode: 'points', maximum_points: 12.5, fixed_properties: [] }))
            methods.openEditDialog.call(ctx, ctx.entries[0])
            expect(ctx.entryForm.maximum_points).toBe(12.5)
            expect(methods.entryPropertyTypeLabel(ctx.entryForm)).toBe('Punkte · maximal 12,5')
            expect((Entries as any).computed.calculationDialogPropertyLabel.call({ calculationDialogEntry: ctx.entryForm })).toBe('Punkte · maximal 12,5')
            methods.selectPropertyMode.call(ctx, 'plus')
            await methods.saveEntry.call(ctx)
            expect(put).toHaveBeenLastCalledWith(expect.any(String), expect.objectContaining({ properties_mode: 'plus', maximum_points: null }))
        } finally { vi.unstubAllGlobals() }
    })

    it.each([[20, { 1: 18, 2: 15, 3: 13, 4: 10 }], [12.5, { 1: 11, 2: 9, 3: 8, 4: 6 }], [90, { 1: 79, 2: 68, 3: 56, 4: 45 }]])('calculates editable whole-point standard percentage thresholds for max %s', (maximum, expected) => {
        const methods = (Entries as any).methods
        const ctx: any = {}
        methods.openCalculationDialog.call(ctx, entryFixture({ properties_mode: 'points', maximum_points: maximum }))
        expect(ctx.calculationDialogPointThresholds).toEqual({ 1: null, 2: null, 3: null, 4: null })
        methods.applyStandardPointThresholds.call(ctx)
        expect(ctx.calculationDialogPointThresholds).toEqual(expected)
        expect((Entries as any).computed.calculationDialogPointThresholdError.call({ ...ctx, calculationDialogUsesPoints: true })).toBe('')
    })

    it('saves manually adjusted standard point thresholds and restores the saved version', async () => {
        const methods = (Entries as any).methods
        const entry = entryFixture({ properties_mode: 'points', maximum_points: 20 })
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { ...entry, ...payload } } }))
        const ctx: any = { replaceGradingEntry: vi.fn(), closeCalculationDialog: methods.closeCalculationDialog, notifyError: vi.fn() }
        vi.stubGlobal('axios', { put })
        try {
            methods.openCalculationDialog.call(ctx, entry)
            methods.applyStandardPointThresholds.call(ctx)
            ctx.calculationDialogPointThresholds[1] = '18,25'
            await methods.saveCalculationDialog.call(ctx)
            expect(put).toHaveBeenCalledWith(expect.any(String), { points_grade_thresholds: { 1: 18.25, 2: 15, 3: 13, 4: 10 } })
            const saved = ctx.replaceGradingEntry.mock.calls[0][0]
            methods.openCalculationDialog.call(ctx, saved)
            expect(ctx.calculationDialogPointThresholds[1]).toBe(18.25)
            ctx.calculationDialogPointThresholds[1] = 19
            methods.closeCalculationDialog.call(ctx)
            methods.openCalculationDialog.call(ctx, saved)
            expect(ctx.calculationDialogPointThresholds[1]).toBe(18.25)
        } finally { vi.unstubAllGlobals() }
    })

    it('requires manual correction if whole-point rounding produces identical thresholds for a small maximum', () => {
        const methods = (Entries as any).methods
        const ctx: any = {}
        methods.openCalculationDialog.call(ctx, entryFixture({ properties_mode: 'points', maximum_points: 2 }))
        methods.applyStandardPointThresholds.call(ctx)
        expect(ctx.calculationDialogPointThresholds).toEqual({ 1: 2, 2: 2, 3: 1, 4: 1 })
        expect((Entries as any).computed.calculationDialogPointThresholdError.call({ ...ctx, calculationDialogUsesPoints: true })).toContain('kleiner')
        ctx.calculationDialogPointThresholds = { 1: '1,75', 2: '1,5', 3: '1,25', 4: '1' }
        expect((Entries as any).computed.calculationDialogPointThresholdError.call({ ...ctx, calculationDialogUsesPoints: true })).toBe('')
    })

    it.each([
        [{ 1: 21, 2: 15, 3: 12, 4: 10 }, 'zwischen 0'],
        [{ 1: 17, 2: 15, 3: 12, 4: -1 }, 'zwischen 0'],
        [{ 1: 17, 2: 17, 3: 12, 4: 10 }, 'kleiner'],
        [{ 1: 10, 2: 12, 3: 15, 4: 17 }, 'kleiner'],
        [{ 1: null, 2: 15, 3: 12, 4: 10 }, 'alle vier'],
    ])('rejects invalid points thresholds %j', async (thresholds, message) => {
        const methods = (Entries as any).methods
        const ctx: any = {}
        methods.openCalculationDialog.call(ctx, entryFixture({ properties_mode: 'points', maximum_points: 20, points_grade_thresholds: thresholds }))
        const put = vi.fn()
        vi.stubGlobal('axios', { put })
        try {
            await methods.saveCalculationDialog.call(ctx)
            expect(put).not.toHaveBeenCalled()
            expect(ctx.calculationDialogErrors.points_grade_thresholds[0]).toContain(message)
        } finally { vi.unstubAllGlobals() }
    })

    it('shows standard grades and enabled additional options with their assigned values', () => {
        const entry = entryFixture({ properties_mode: 'fixed', fixed_properties: ['1', '2', '3', '4', '5'], calculation_mode: 'grades',
            enabled_special_properties: ['NA', 'F'], property_evaluations: [{ property: 'F', evaluation: 'ignored' }, { property: 'VL', evaluation: 5 }],
        })
        const ctx = { calculationDialogEntry: entry, propertyEvaluationLabel: (Entries as any).methods.propertyEvaluationLabel }
        expect((Entries as any).computed.calculationDialogUsesStandardGrades.call(ctx)).toBe(true)
        const options = (Entries as any).computed.calculationDialogAdditionalProperties.call(ctx)
        expect(options.map((option) => option.label)).toEqual(['NA – Nicht angetreten', 'F – Gefehlt'])
        expect(options[0].evaluation).toBe('')
        expect(options[1].evaluation).toContain('Nicht berücksichtigen')
        expect((Entries as any).computed.calculationDialogAdditionalProperties.call({ ...ctx, calculationDialogEntry: { ...entry, enabled_special_properties: [], property_evaluations: [] } })).toEqual([])
    })

    it.each(['deficit_points', 'points'])('saves decimal comma thresholds for %s and preserves the inactive draft', async (mode) => {
        const methods = (Entries as any).methods
        const missing = { 1: '0', 2: '0,5', 3: '1,5', 4: '3,25' }
        const points = { 1: '8,5', 2: '6,25', 3: '4', 4: '-0,5' }
        const key = mode === 'deficit_points' ? 'free_deficit_grade_thresholds' : 'free_points_grade_thresholds'
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { ...entryFixture({ properties_mode: 'free' }), ...payload } } }))
        const ctx: any = { replaceGradingEntry: vi.fn(), closeCalculationDialog: methods.closeCalculationDialog, notifyError: vi.fn() }
        vi.stubGlobal('axios', { put })
        try {
            methods.openCalculationDialog.call(ctx, entryFixture({ properties_mode: 'free' }))
            expect(ctx.calculationDialogFreeGradingMode).toBe('deficit_points')
            ctx.calculationDialogFreeDeficitThresholds = missing
            ctx.calculationDialogFreePointThresholds = points
            ctx.calculationDialogFreeGradingMode = mode
            const thresholds = (Entries as any).computed.calculationDialogFreeThresholds.call(ctx)
            expect((Entries as any).computed.calculationDialogFreeThresholdError.call({ ...ctx, calculationDialogUsesFreeGrading: true, calculationDialogFreeThresholds: thresholds })).toBe('')
            await methods.saveCalculationDialog.call(ctx)
            expect(put).toHaveBeenCalledWith(expect.any(String), { free_grading_mode: mode,
                [key]: mode === 'deficit_points' ? { 1: 0, 2: 0.5, 3: 1.5, 4: 3.25 } : { 1: 8.5, 2: 6.25, 3: 4, 4: -0.5 },
            })
            expect(ctx.calculationDialogFreeDeficitThresholds).toEqual(missing)
            expect(ctx.calculationDialogFreePointThresholds).toEqual(points)
        } finally { vi.unstubAllGlobals() }
    })

    it.each([
        ['deficit_points', { 1: 0, 2: 0, 3: 2, 4: 3 }, 'größer'],
        ['deficit_points', { 1: -0.5, 2: 1, 3: 2, 4: 3 }, 'ab 0'],
        ['deficit_points', { 1: 3, 2: 2, 3: 1, 4: 0 }, 'größer'],
        ['points', { 1: 0, 2: 1, 3: 2, 4: 3 }, 'kleiner'],
        ['points', { 1: 3, 2: 2, 3: 2, 4: 0 }, 'kleiner'],
        ['points', { 1: 'x', 2: 2, 3: 1, 4: 0 }, 'Zahlen'],
        ['points', { 1: 3, 2: '', 3: 1, 4: 0 }, 'alle vier'],
    ])('blocks invalid %s thresholds %j', async (mode, thresholds, error) => {
        const methods = (Entries as any).methods
        const ctx: any = {}
        methods.openCalculationDialog.call(ctx, entryFixture({ properties_mode: 'free' }))
        ctx.calculationDialogFreeGradingMode = mode
        ctx.calculationDialogFreeDeficitThresholds = thresholds
        ctx.calculationDialogFreePointThresholds = thresholds
        const put = vi.fn()
        vi.stubGlobal('axios', { put })
        try {
            await methods.saveCalculationDialog.call(ctx)
            expect(put).not.toHaveBeenCalled()
            expect(Object.values(ctx.calculationDialogErrors).flat().join(' ')).toContain(error)
        } finally { vi.unstubAllGlobals() }
    })

    it('allows free grading for custom legacy choices and keeps draft changes isolated', () => {
        const methods = (Entries as any).methods
        const original = entryFixture({ fixed_properties: ['erledigt', 'nicht erledigt', 'teilweise'], free_grading_mode: 'points', free_points_grade_thresholds: { 1: 8.5, 2: 6, 3: 3, 4: 1 } })
        const ctx: any = {}
        methods.openCalculationDialog.call(ctx, original)
        expect((Entries as any).computed.calculationDialogUsesFreeGrading.call(ctx)).toBe(true)
        ctx.calculationDialogFreePointThresholds[1] = '10,5'
        expect(original.free_points_grade_thresholds[1]).toBe(8.5)
        methods.closeCalculationDialog.call(ctx)
        methods.openCalculationDialog.call(ctx, original)
        expect(ctx.calculationDialogFreePointThresholds[1]).toBe(8.5)
        for (const entry of [entryFixture({ properties_mode: 'plus' }), entryFixture({ fixed_properties: ['1', '2', '3', '4', '5'] })]) {
            expect((Entries as any).computed.calculationDialogUsesFreeGrading.call({ calculationDialogEntry: entry })).toBe(false)
        }
    })

    it('keeps decimal comma drafts while entering property values and saves numeric mappings', async () => {
        const methods = (Entries as any).methods
        const ctx: any = { entries: [], canSaveEntry: true, normalizeShortName: methods.normalizeShortName, closeEditDialog: methods.closeEditDialog, notifyError: vi.fn() }
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { id: 1, ...payload } } }))
        vi.stubGlobal('axios', { put })
        try {
            methods.openEditDialog.call(ctx, entryFixture({ properties_mode: 'free', fixed_properties: ['teilweise'] }))
            methods.setEntryPropertyValue.call(ctx, 'teilweise', '0,')
            expect(methods.entryPropertyValue.call(ctx, 'teilweise')).toBe('0,')
            methods.setEntryPropertyValue.call(ctx, 'teilweise', '0,25')
            expect((Entries as any).computed.entryPropertyValuesError.call({ ...ctx, editableEntryProperties: ['teilweise'] })).toBe('')
            await methods.saveEntry.call(ctx)
            expect(put).toHaveBeenCalledWith(expect.any(String), expect.objectContaining({ property_evaluations: [{ property: 'teilweise', evaluation: 0.25 }] }))
        } finally { vi.unstubAllGlobals() }
    })

    it('shows five contiguous standard-percentage bands with equal passing widths and clear boundaries', () => {
        const bands = (Entries as any).data().standardPercentageGrades
        expect(bands.map((band) => [band.min, band.max, band.grade])).toEqual([
            [87.5, 100, 1], [75, 87.5, 2], [62.5, 75, 3], [50, 62.5, 4], [0, 50, 5],
        ])
        expect(bands.filter((band) => band.grade < 5).every((band) => band.max - band.min === 12.5)).toBe(true)
        expect(bands.map((band) => (Entries as any).methods.standardPercentageRange(band))).toEqual([
            '87,5 % bis 100 %', '75 % bis unter 87,5 %', '62,5 % bis unter 75 %', '50 % bis unter 62,5 %', 'Unter 50 %',
        ])
        expect(bands.map((band) => band.label)).toEqual(['Sehr gut', 'Gut', 'Befriedigend', 'Genügend', 'Nicht genügend'])
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')
        expect(source).toContain('<table v-if="calculationDialogMaximumPlusGradingMode === \'standard_percentage\'"')
        expect(source).toContain('v-for="band in standardPercentageGrades"')
    })

    it.each(['fixed', 'free', 'plus_minus', 'plus'])('defaults special options on and persists deselection for %s', async (mode) => {
        const methods = (Entries as any).methods
        const original = entryFixture({ properties_mode: mode, fixed_properties: mode === 'fixed' ? ['1', '2', '3', '4', '5'] : [] })
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { id: 1, ...payload } } }))
        const ctx: any = { entries: [], canSaveEntry: true, normalizeShortName: methods.normalizeShortName,
            closeEditDialog: methods.closeEditDialog, notifyError: vi.fn(),
        }
        vi.stubGlobal('axios', { put })
        try {
            methods.openEditDialog.call(ctx, original)
            expect(ctx.entryForm.enabled_special_properties).toEqual(['NA', 'VL', 'F'])
            ctx.entryForm.enabled_special_properties = ['NA', 'F']
            await methods.saveEntry.call(ctx)
            expect(put).toHaveBeenLastCalledWith(expect.any(String), expect.objectContaining({ enabled_special_properties: ['NA', 'F'] }))
            methods.openEditDialog.call(ctx, ctx.entries[0])
            expect(ctx.entryForm.enabled_special_properties).toEqual(['NA', 'F'])
            ctx.entryForm.enabled_special_properties = []
            await methods.saveEntry.call(ctx)
            methods.openEditDialog.call(ctx, ctx.entries[0])
            expect(ctx.entryForm.enabled_special_properties).toEqual([])
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('defaults new special checkboxes on and keeps edited selections isolated', () => {
        const methods = (Entries as any).methods
        const draft = methods.createEmptyEntry.call({ activeCategory: 'Benotung', activeAreaId: 10 })
        expect(draft.enabled_special_properties).toEqual(['NA', 'VL', 'F'])
        const original = entryFixture({ enabled_special_properties: ['F'] })
        const ctx: any = {}
        methods.openEditDialog.call(ctx, original)
        ctx.entryForm.enabled_special_properties.push('NA')
        expect(original.enabled_special_properties).toEqual(['F'])
        expect(methods.enabledSpecialPropertyOptions(original).map((option) => option.value)).toEqual(['F'])
    })

    it('removes disabled reserved mappings from the submitted free-property values', async () => {
        const methods = (Entries as any).methods
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { id: 1, ...payload } } }))
        const ctx: any = { entries: [], canSaveEntry: true, normalizeShortName: methods.normalizeShortName,
            closeEditDialog: methods.closeEditDialog, notifyError: vi.fn(),
        }
        vi.stubGlobal('axios', { put })
        try {
            methods.openEditDialog.call(ctx, entryFixture({ properties_mode: 'free', fixed_properties: ['F', 'erledigt'],
                property_evaluations: [{ property: 'F', evaluation: 0 }, { property: 'erledigt', evaluation: 1 }],
            }))
            ctx.entryForm.enabled_special_properties = []
            await methods.saveEntry.call(ctx)
            expect(put).toHaveBeenCalledWith(expect.any(String), expect.objectContaining({
                enabled_special_properties: [], property_evaluations: [{ property: 'erledigt', evaluation: 1 }],
            }))
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it.each([
        ['free', ['erledigt', 'nicht erledigt', 'gefehlt'], 'Freie Eingabe'],
        ['fixed', ['erledigt', 'nicht erledigt', 'gefehlt'], 'Freie Eingabe'],
        ['fixed', ['1', '2', '3', '4', '5'], 'Standardnoten'],
        ['plus', [], 'Nur Plus'], ['plus_minus', [], 'Plus und Minus'],
    ])('labels the overview property type %s independently of its values', (mode, properties, label) => {
        expect((Entries as any).methods.entryPropertyTypeLabel.call((Entries as any).methods,
            entryFixture({ properties_mode: mode, fixed_properties: properties }))).toBe(label)
    })

    it.each([
        ['Auftrag', 'plus', 'Nur Plus'], ['Mitarbeit', 'plus_minus', 'Plus und Minus'],
    ])('labels %s from its allowed properties rather than the shared sign calculation', (name, mode, label) => {
        const entry = entryFixture({ name, properties_mode: mode, calculation_mode: 'plus_minus', fixed_properties: [] })
        expect((Entries as any).methods.standardCalculationLabel(entry)).toBe(label)
        expect((Entries as any).computed.calculationDialogPropertyLabel.call({ calculationDialogEntry: entry })).toBe(label)
    })

    it('does not show a standard calculation label for free input or disabled properties', () => {
        const label = (Entries as any).methods.standardCalculationLabel
        expect(label(entryFixture({ properties_mode: 'free', calculation_mode: 'grades' }))).toBe('')
        expect(label(entryFixture({ properties_mode: 'plus', has_properties: false }))).toBe('')
    })

    it.each([
        ['fixed', 'Standardnoten', ['1', '2', '3', '4', '5']],
        ['free', 'Freie Eingabe', []], ['plus', 'Nur Plus', []], ['plus_minus', 'Plus und Minus', []],
    ])('opens a persistent calculation dialog for %s entries', (mode, label, properties) => {
        const ctx: any = {}
        const entry = entryFixture({ properties_mode: mode, fixed_properties: properties })
        ;(Entries as any).methods.openCalculationDialog.call(ctx, entry)
        expect(ctx.calculationDialogOpen).toBe(true)
        expect(ctx.calculationDialogEntry).toBe(entry)
        expect((Entries as any).computed.calculationDialogPropertyLabel.call(ctx)).toBe(label)
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')
        expect(source).toContain('<v-dialog v-model="calculationDialogOpen" persistent')
        expect(source).toContain('@click="openCalculationDialog(entry)"')
        expect(source).toContain('v-if="calculationDialogEntry?.properties_mode === \'plus\'"')
    })

    it.each([true, false])('saves the maximum-plus permission as %s without property mappings', async (allowed) => {
        const methods = (Entries as any).methods
        const thresholds = { 1: 8, 2: 6, 3: 4, 4: 2 }
        const saved = entryFixture({ properties_mode: 'plus', allows_maximum_plus: allowed, maximum_plus_grade_thresholds: thresholds })
        const put = vi.fn().mockResolvedValue({ data: { data: saved } })
        vi.stubGlobal('axios', { put })
        const ctx: any = { replaceGradingEntry: vi.fn(), closeCalculationDialog: methods.closeCalculationDialog, notifyError: vi.fn() }
        try {
            methods.openCalculationDialog.call(ctx, { ...saved, allows_maximum_plus: !allowed })
            ctx.calculationDialogAllowsMaximumPlus = allowed
            await methods.saveCalculationDialog.call(ctx)
            expect(put).toHaveBeenCalledWith('/api/admin/teaching/entry_definitions/1/calculation-settings', {
                allows_maximum_plus: allowed, maximum_plus_grading_mode: allowed ? 'standard_percentage' : 'other',
                sum_plus_evaluations: false,
                ...(!allowed ? { maximum_plus_grade_thresholds: thresholds } : {}),
            })
            expect(ctx.replaceGradingEntry).toHaveBeenCalledWith(saved)
            expect(ctx.calculationDialogOpen).toBe(false)
            expect(ctx.isSavingCalculationDialog).toBe(false)
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('discards unsaved maximum-plus changes when the dialog is closed', () => {
        const methods = (Entries as any).methods
        const entry = entryFixture({ properties_mode: 'plus', allows_maximum_plus: false })
        const ctx: any = {}
        methods.openCalculationDialog.call(ctx, entry)
        ctx.calculationDialogAllowsMaximumPlus = true
        methods.closeCalculationDialog.call(ctx)
        methods.openCalculationDialog.call(ctx, entry)
        expect(ctx.calculationDialogAllowsMaximumPlus).toBe(false)
        expect(entry.allows_maximum_plus).toBe(false)
    })

    it.each([[false, false], [false, true], [true, false], [true, true]])('saves summing %s independently of maximum-plus %s and restores it', async (sum, maximum) => {
        const methods = (Entries as any).methods
        const original = entryFixture({ properties_mode: 'plus', allows_maximum_plus: maximum,
            maximum_plus_grade_thresholds: { 1: 8, 2: 6, 3: 4, 4: 2 } })
        const ctx: any = { replaceGradingEntry: vi.fn(), closeCalculationDialog: methods.closeCalculationDialog, notifyError: vi.fn() }
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { ...original, ...payload } } }))
        vi.stubGlobal('axios', { put })
        try {
            methods.openCalculationDialog.call(ctx, original)
            expect(ctx.calculationDialogSumPlusEvaluations).toBe(false)
            ctx.calculationDialogSumPlusEvaluations = sum
            await methods.saveCalculationDialog.call(ctx)
            expect(put).toHaveBeenCalledWith(expect.any(String), expect.objectContaining({
                sum_plus_evaluations: sum, allows_maximum_plus: maximum,
            }))
            const saved = ctx.replaceGradingEntry.mock.calls[0][0]
            methods.openCalculationDialog.call(ctx, saved)
            expect(ctx.calculationDialogSumPlusEvaluations).toBe(sum)
            ctx.calculationDialogSumPlusEvaluations = !sum
            methods.closeCalculationDialog.call(ctx)
            methods.openCalculationDialog.call(ctx, saved)
            expect(ctx.calculationDialogSumPlusEvaluations).toBe(sum)
            expect(saved.sum_plus_evaluations).toBe(sum)
        } finally { vi.unstubAllGlobals() }
    })

    it('offers summing and individual grading as two exclusive fields using the existing grading selector design', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')
        expect(source).toMatch(/<v-btn-toggle\s+v-model="calculationDialogSumPlusEvaluations"\s+class="maximum-plus-grading-options"[\s\S]*?\smandatory\s/)
        expect(source).toContain('<v-btn :value="true">Einzelbewertungen zusammenzählen</v-btn>')
        expect(source).toContain('<v-btn :value="false">Jede Einzelbewertung extra werten</v-btn>')
    })

    it.each(['standard_percentage', 'other'])('persists and restores maximum-plus grading choice %s', async (mode) => {
        const methods = (Entries as any).methods
        const thresholds = { 1: 8, 2: 6, 3: 4, 4: 2 }
        const entry = entryFixture({ properties_mode: 'plus', allows_maximum_plus: true, maximum_plus_grading_mode: mode, maximum_plus_grade_thresholds: thresholds })
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { ...entry, ...payload } } }))
        const ctx: any = { replaceGradingEntry: vi.fn(), closeCalculationDialog: methods.closeCalculationDialog, notifyError: vi.fn() }
        vi.stubGlobal('axios', { put })
        try {
            methods.openCalculationDialog.call(ctx, { ...entry, maximum_plus_grading_mode: null })
            expect(ctx.calculationDialogMaximumPlusGradingMode).toBe('standard_percentage')
            ctx.calculationDialogMaximumPlusGradingMode = mode
            await methods.saveCalculationDialog.call(ctx)
            expect(put).toHaveBeenCalledWith(expect.any(String), { allows_maximum_plus: true, maximum_plus_grading_mode: mode,
                sum_plus_evaluations: false,
                ...(mode === 'other' ? { maximum_plus_grade_thresholds: thresholds } : {}),
            })
            const saved = ctx.replaceGradingEntry.mock.calls[0][0]
            methods.openCalculationDialog.call(ctx, saved)
            expect(ctx.calculationDialogMaximumPlusGradingMode).toBe(mode)
            ctx.calculationDialogMaximumPlusGradingMode = mode === 'other' ? 'standard_percentage' : 'other'
            methods.closeCalculationDialog.call(ctx)
            methods.openCalculationDialog.call(ctx, saved)
            expect(ctx.calculationDialogMaximumPlusGradingMode).toBe(mode)
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('only offers mutually exclusive grading options when maximum plus is enabled', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')
        expect(source).toContain('v-if="calculationDialogEntry?.properties_mode === \'plus\' && calculationDialogAllowsMaximumPlus"')
        expect(source).toContain('v-model="calculationDialogMaximumPlusGradingMode"')
        expect(source).toContain('<v-btn value="standard_percentage">Benotung durch Standardprozent</v-btn>')
        expect(source).toContain('<v-btn value="other">Andere Benotung</v-btn>')
        expect(source).toMatch(/<v-btn-toggle\s+v-model="calculationDialogMaximumPlusGradingMode"[\s\S]*?\smandatory\s/)
        expect(source).toMatch(/\.maximum-plus-grading-options \{[^}]*grid-template-columns: repeat\(2, minmax\(0, 1fr\)\)/)
    })

    it.each([
        ['plus', false, 'standard_percentage', true], ['plus', false, 'other', true],
        ['plus', true, 'other', true], ['plus', true, 'standard_percentage', false],
        ['free', false, 'other', false], ['plus_minus', false, 'other', false],
    ])('selects custom thresholds for %s with maximum %s and mode %s', (mode, allowed, gradingMode, expected) => {
        expect((Entries as any).computed.calculationDialogUsesOtherGrading.call({
            calculationDialogEntry: entryFixture({ properties_mode: mode }), calculationDialogAllowsMaximumPlus: allowed,
            calculationDialogMaximumPlusGradingMode: gradingMode,
        })).toBe(expected)
    })

    it.each([[null, 'Unter der Grenze für Genügend'], ['', 'Unter der Grenze für Genügend'], [2, 'Weniger als 2 Plus'], ['12', 'Weniger als 12 Plus']])('previews the failing grade for minimum %s', (minimum, label) => {
        expect((Entries as any).computed.calculationDialogFailingGradeLabel.call({ calculationDialogGradeThresholds: { 4: minimum } })).toBe(label)
    })

    it.each([
        [{ 1: null, 2: 6, 3: 4, 4: 2 }, 'alle vier'],
        [{ 1: '', 2: 6, 3: 4, 4: 2 }, 'alle vier'],
        [{ 1: 8.5, 2: 6, 3: 4, 4: 2 }, 'ganze Zahlen'],
        [{ 1: 8, 2: 6, 3: 4, 4: -1 }, 'ganze Zahlen'],
        [{ 1: 8, 2: 8, 3: 4, 4: 2 }, 'kleiner'],
        [{ 1: 2, 2: 4, 3: 6, 4: 8 }, 'kleiner'],
    ])('rejects invalid custom thresholds %j without saving', async (thresholds, message) => {
        const methods = (Entries as any).methods
        const put = vi.fn()
        vi.stubGlobal('axios', { put })
        try {
            const ctx: any = {}
            methods.openCalculationDialog.call(ctx, entryFixture({ properties_mode: 'plus', allows_maximum_plus: false, maximum_plus_grade_thresholds: thresholds }))
            expect((Entries as any).computed.calculationDialogThresholdError.call({ ...ctx, calculationDialogUsesOtherGrading: true })).toContain(message)
            await methods.saveCalculationDialog.call(ctx)
            expect(put).not.toHaveBeenCalled()
            expect(ctx.calculationDialogErrors.maximum_plus_grade_thresholds[0]).toContain(message)
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('saves threshold inputs numerically without maximum and discards unsaved changes', async () => {
        const methods = (Entries as any).methods
        const original = entryFixture({ properties_mode: 'plus', allows_maximum_plus: false, maximum_plus_grade_thresholds: { 1: 8, 2: 6, 3: 4, 4: 2 } })
        const ctx: any = { replaceGradingEntry: vi.fn(), closeCalculationDialog: methods.closeCalculationDialog, notifyError: vi.fn() }
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { ...original, ...payload } } }))
        vi.stubGlobal('axios', { put })
        try {
            methods.openCalculationDialog.call(ctx, original)
            ctx.calculationDialogGradeThresholds[1] = '10'
            expect(original.maximum_plus_grade_thresholds[1]).toBe(8)
            methods.closeCalculationDialog.call(ctx)
            methods.openCalculationDialog.call(ctx, original)
            expect(ctx.calculationDialogGradeThresholds[1]).toBe(8)
            ctx.calculationDialogGradeThresholds = { 1: '10', 2: '7', 3: '3', 4: '0' }
            await methods.saveCalculationDialog.call(ctx)
            expect(put).toHaveBeenCalledWith(expect.any(String), {
                allows_maximum_plus: false, maximum_plus_grading_mode: 'other',
                sum_plus_evaluations: false,
                maximum_plus_grade_thresholds: { 1: 10, 2: 7, 3: 3, 4: 0 },
            })
            methods.openCalculationDialog.call(ctx, ctx.replaceGradingEntry.mock.calls[0][0])
            expect(ctx.calculationDialogGradeThresholds).toEqual({ 1: 10, 2: 7, 3: 3, 4: 0 })
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('marks legacy Typewriter choices as free input and saves their inline values together', async () => {
        const methods = (Entries as any).methods
        const original = entryFixture({ name: 'Typewriter', fixed_properties: ['erledigt', 'nicht erledigt', 'gefehlt'],
            property_evaluations: [{ property: 'erledigt', evaluation: 1 }],
        })
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { id: 1, ...payload } } }))
        const ctx: any = { entries: [], canSaveEntry: true, normalizeShortName: methods.normalizeShortName,
            closeEditDialog: methods.closeEditDialog, notifyError: vi.fn(),
        }
        vi.stubGlobal('axios', { put })
        try {
            methods.openEditDialog.call(ctx, original)
            expect((Entries as any).computed.selectedPropertyMode.call(ctx)).toBe('free')
            expect(ctx.entryForm.properties_mode).toBe('free')
            expect(methods.entryPropertyValue.call(ctx, 'erledigt')).toBe(1)
            methods.setEntryPropertyValue.call(ctx, 'erledigt', '2')
            methods.setEntryPropertyValue.call(ctx, 'nicht erledigt', '-1')
            methods.setEntryPropertyValue.call(ctx, 'gefehlt', 'ignored')
            expect(original.property_evaluations).toEqual([{ property: 'erledigt', evaluation: 1 }])
            await methods.saveEntry.call(ctx)
            expect(put).toHaveBeenCalledTimes(1)
            expect(put).toHaveBeenCalledWith(expect.any(String), expect.objectContaining({
                properties_mode: 'free', property_evaluations: [
                    { property: 'erledigt', evaluation: 2 }, { property: 'nicht erledigt', evaluation: -1 },
                    { property: 'gefehlt', evaluation: 'ignored' },
                ],
            }))
            expect(ctx.notifyError).not.toHaveBeenCalled()
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('allows clearing an inline value and rejects invalid numeric input', () => {
        const methods = (Entries as any).methods
        const ctx: any = { entryForm: entryFixture({ properties_mode: 'free' }), editableEntryProperties: ['+'] }
        methods.setEntryPropertyValue.call(ctx, '+', 'invalid')
        expect((Entries as any).computed.entryPropertyValuesError.call(ctx)).toBe('Bitte gültige Zahlenwerte eingeben.')
        methods.setEntryPropertyValue.call(ctx, '+', '')
        expect(methods.entryPropertyValue.call(ctx, '+')).toBeNull()
        expect((Entries as any).computed.entryPropertyValuesError.call(ctx)).toBe('')
    })

    it('saves free properties and makes them available for value assignments', async () => {
        const methods = (Entries as any).methods
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { id: 1, ...payload } } }))
        vi.stubGlobal('axios', { put })
        const ctx: any = {
            areas: [{ id: 10 }], entries: [], activeAreaId: 10, activeCategory: 'Benotung',
            canSaveEntry: true, isSaving: false,
            normalizeShortName: methods.normalizeShortName,
            closeEditDialog: methods.closeEditDialog,
            notifyError: vi.fn(), replaceGradingEntry: vi.fn(),
        }
        try {
            methods.openEditDialog.call(ctx, entryFixture({ properties_mode: 'free', has_properties: false }))
            expect(ctx.entryForm.has_properties).toBe(true)
            ctx.entryForm.fixed_properties = [' Erledigt ', 'Fehlt', 'Erledigt']
            await methods.saveEntry.call(ctx)
            expect(put).toHaveBeenLastCalledWith(expect.any(String), expect.objectContaining({
                has_properties: true, properties_mode: 'free', fixed_properties: ['Erledigt', 'Fehlt'],
            }))
            methods.openEditDialog.call(ctx, ctx.entries[0])
            expect(methods.entryPropertyValue.call(ctx, 'Erledigt')).toBeNull()
            expect(methods.entryPropertyValue.call(ctx, 'Fehlt')).toBeNull()
            methods.setEntryPropertyValue.call(ctx, 'Erledigt', 2)
            methods.setEntryPropertyValue.call(ctx, 'Fehlt', -1)
            await methods.saveEntry.call(ctx)
            expect(put).toHaveBeenLastCalledWith(expect.stringContaining('entry_definitions/1'), expect.objectContaining({
                property_evaluations: [{ property: 'Erledigt', evaluation: 2 }, { property: 'Fehlt', evaluation: -1 }],
            }))
            expect(ctx.notifyError).not.toHaveBeenCalled()
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('merges free property suggestions with existing value mappings without duplicates', () => {
        const ctx: any = {}
        const entry = entryFixture({ properties_mode: 'free', fixed_properties: ['Erledigt', 'Fehlt'],
            property_evaluations: [{ property: 'Erledigt', evaluation: 2 }, { property: 'Extra', evaluation: 3 }],
        })
        ;(Entries as any).methods.openEditDialog.call(ctx, entry)
        expect(ctx.entryForm.fixed_properties).toEqual(['Erledigt', 'Fehlt', 'Extra'])
        expect((Entries as any).methods.entryPropertyValue.call(ctx, 'Erledigt')).toBe(2)
        expect((Entries as any).methods.entryPropertyValue.call(ctx, 'Fehlt')).toBeNull()
        expect((Entries as any).methods.entryPropertyValue.call(ctx, 'Extra')).toBe(3)
        expect((Entries as any).methods.calculationProperties(entry)).toEqual(['Erledigt', 'Fehlt', 'Extra'])
    })

    it.each(['Benotung', 'Verhalten', 'Weitere'])('enables properties only for new Benotung entries (%s)', (category) => {
        const entry = (Entries as any).methods.createEmptyEntry.call({ activeCategory: category, activeAreaId: 10 })
        expect(entry.has_properties).toBe(category === 'Benotung')
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')
        expect(source).not.toContain('<v-switch v-model="entryForm.has_properties"')
    })

    it('does not offer a second calculation mode selection', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')
        expect(source).not.toContain('calculation-mode-options')
        expect(source).not.toContain("@click=\"calculationMode =")
        expect(source).not.toContain('calculationEntryDialogOpen')
        expect(source).not.toContain('openCalculationEntryDialog')
        expect(source).toContain('setEntryPropertyValue(property, $event)')
        expect(source).toContain('entryPropertyValuesError')
    })

    it.each([
        ['plus', 'plus_minus'], ['plus_minus', 'plus_minus'], ['fixed', 'grades'],
    ])('uses the derived %s calculation with a read-only summary', (propertyMode, calculationMode) => {
        const methods = (Entries as any).methods
        const ctx: any = {}
        const entry = entryFixture({
            properties_mode: propertyMode, calculation_mode: calculationMode,
            fixed_properties: ['1', '2', '3', '4', '5'],
        })
        methods.openEditDialog.call(ctx, entry)
        expect(ctx.entryForm.properties_mode).toBe(propertyMode)
        expect(methods.standardCalculationLabel.call({}, entry)).toBe(
            propertyMode === 'plus' ? 'Nur Plus' : propertyMode === 'plus_minus' ? 'Plus und Minus' : 'Standardnoten'
        )
    })

    it('selects exactly the five standard grades and restores the selection when reopened', () => {
        const methods = (Entries as any).methods
        const ctx: any = { entryForm: entryFixture() }
        methods.selectPropertyMode.call(ctx, 'grades')
        expect(ctx.entryForm.properties_mode).toBe('fixed')
        expect(ctx.entryForm.fixed_properties).toEqual(['1', '2', '3', '4', '5'])
        methods.openEditDialog.call(ctx, entryFixture({ ...ctx.entryForm }))
        expect((Entries as any).computed.selectedPropertyMode.call(ctx)).toBe('grades')
        methods.selectPropertyMode.call(ctx, 'plus')
        expect((Entries as any).computed.selectedPropertyMode.call(ctx)).toBe('plus')
    })

    it('marks existing custom choices as free input while preserving their properties', () => {
        const ctx: any = { entryForm: entryFixture({ fixed_properties: ['Gut', 'Sehr gut'] }) }
        expect((Entries as any).computed.selectedPropertyMode.call(ctx)).toBe('free')
        expect(ctx.entryForm.fixed_properties).toEqual(['Gut', 'Sehr gut'])
    })

    it.each(['plus', 'plus_minus'])('preserves the %s property mode when editing and saving', async (mode) => {
        const methods = (Entries as any).methods
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { id: 1, ...payload } } }))
        ;(globalThis as any).axios = { put }
        const ctx: any = {
            areas: [{ id: 10, name: 'Unterstufe' }],
            entries: [],
            activeAreaId: 10,
            activeCategory: 'Benotung',
            canSaveEntry: true,
            isSaving: false,
            normalizeShortName: methods.normalizeShortName,
            closeEditDialog: methods.closeEditDialog,
            notifyError: vi.fn(),
        }
        methods.openEditDialog.call(ctx, entryFixture({ properties_mode: mode }))
        expect(ctx.entryForm.properties_mode).toBe(mode)
        await methods.saveEntry.call(ctx)
        expect(put).toHaveBeenCalledWith(expect.any(String), expect.objectContaining({
            properties_mode: mode, has_properties: true, fixed_properties: [],
        }))
        expect(ctx.notifyError).not.toHaveBeenCalled()
    })

    it.each([
        ['plus', 'Nur Plus'], ['plus_minus', 'Plus und Minus'],
        ['free', 'Freie Eingabe'], ['fixed', 'Feste Auswahl'],
    ])('labels the %s property mode as %s', (mode, label) => {
        expect((Entries as any).methods.propertyModeLabel(mode)).toBe(label)
    })

    it.each([
        ['plus', '+++'], ['plus', '--'], ['plus', '+-'],
        ['plus_minus', '+++'], ['plus_minus', '---'],
        ['plus_minus', '+-'], ['plus_minus', 'Text'],
    ])('keeps %s standard mode separate from legacy mapping %s', (mode, property) => {
        const methods = (Entries as any).methods
        const ctx: any = {}
        methods.openEditDialog.call(ctx, entryFixture({
            properties_mode: mode, fixed_properties: [],
            property_evaluations: [{ property, evaluation: 1 }],
        }))
        expect((Entries as any).computed.selectedPropertyMode.call(ctx)).toBe(mode)
        expect((Entries as any).computed.entryPropertyValuesError.call(ctx)).toBe('')
    })

    it('shows an extra mapped to grade 5 as a negative grade without a plus sign', () => {
        const entry = { calculation_mode: 'grades', property_evaluations: [{ property: 'F', evaluation: 5 }] }
        expect((Entries as any).methods.propertyEvaluationLabel.call({}, entry, 'F')).toBe('Note 5')
        expect((Entries as any).methods.propertyEvaluationClass.call({}, entry, 'F')).toBe('calculation-evaluation--negative')
    })

    it('does not label a custom property list as standard grades even with stale calculation metadata', () => {
        const methods = (Entries as any).methods
        const ctx: any = {}
        const entry = entryFixture({ calculation_mode: 'grades', fixed_properties: ['1', '2', '3', '4', '5', 'F'], property_evaluations: [] })
        methods.openEditDialog.call(ctx, entry)
        expect(ctx.entryForm.fixed_properties).toContain('F')
        expect(methods.entryPropertyValue.call(ctx, 'F')).toBeNull()
        expect(methods.standardCalculationLabel.call({}, entry)).toBe('')
    })


    it('keeps predefined extras available for inline editing without retyping them', () => {
        const methods = (Entries as any).methods
        const ctx: any = {}
        const entry = entryFixture({
            calculation_mode: 'plus_minus', properties_mode: 'fixed',
            fixed_properties: ['++++', '+++', '++', '+', '0', 'F'], property_evaluations: [],
        })
        methods.openEditDialog.call(ctx, entry)
        expect(ctx.entryForm.fixed_properties).toEqual(entry.fixed_properties)
        expect(methods.entryPropertyValue.call(ctx, 'F')).toBeNull()
        methods.openEditDialog.call(ctx, { ...entry, property_evaluations: [{ property: 'F', evaluation: 'ignored' }] })
        expect(methods.entryPropertyValue.call(ctx, 'F')).toBe('ignored')
    })


    it('loads free input mappings only from the selected entry', () => {
        const ctx: any = {}
        const mappings = [{ property: '++++', evaluation: 4 }, { property: '~', evaluation: 0 }, { property: 'x', evaluation: 'ignored' }]
        const open = (Entries as any).methods.openEditDialog
        open.call(ctx, entryFixture({ properties_mode: 'free', fixed_properties: [], property_evaluations: mappings }))
        expect(ctx.entryForm.property_evaluations).toEqual(mappings)
        open.call(ctx, entryFixture({ id: 2, properties_mode: 'free', fixed_properties: [], property_evaluations: [] }))
        expect(ctx.entryForm.property_evaluations).toEqual([])
    })

    it.each([
        [[{ property: '++++', evaluation: 4 }, { property: '~', evaluation: 0 }], true],
        [[{ property: 'x', evaluation: 'ignored' }], true],
        [[{ property: '+', evaluation: 'invalid' }], false],
        [[{ property: '+', evaluation: null }], true],
        [[{ property: '+', evaluation: 1 }, { property: '-', evaluation: -2 }], true],
        [[{ property: '+', evaluation: Infinity }], false],
    ])('validates free input mappings %j', (form, valid) => {
        const result = (Entries as any).computed.entryPropertyValuesError.call({
            entryForm: entryFixture({ properties_mode: 'free', property_evaluations: form }),
            editableEntryProperties: form.map((item) => item.property),
        })
        expect(result === '').toBe(valid)
    })

    it.each([
        ['positive', '+1'], ['negative', '-1'], ['neutral', '0'], [0, '0'], [4, '+4'], [-2.5, '-2,5'],
        ['ignored', 'NB'], [null, ''],
    ])('shows the configured evaluation %s in the overview', (evaluation, expected) => {
        const entry = { property_evaluations: evaluation !== null ? [{ property: 'erledigt', evaluation }] : [] }
        expect((Entries as any).methods.propertyEvaluationLabel.call({}, entry, 'erledigt')).toBe(expected)
    })

    it('loads classifications without guessing from property names and discards unsaved edits when reopened', () => {
        const ctx: any = {}
        const entry = entryFixture({
            properties_mode: 'fixed', fixed_properties: ['erledigt', 'nicht erledigt', 'gefehlt'],
            property_evaluations: [{ property: 'gefehlt', evaluation: 'neutral' }],
        })
        const open = (Entries as any).methods.openEditDialog
        open.call(ctx, entry)
        expect((Entries as any).methods.entryPropertyValue.call(ctx, 'erledigt')).toBeNull()
        expect((Entries as any).methods.entryPropertyValue.call(ctx, 'nicht erledigt')).toBeNull()
        expect((Entries as any).methods.entryPropertyValue.call(ctx, 'gefehlt')).toBe(0)
        ;(Entries as any).methods.setEntryPropertyValue.call(ctx, 'erledigt', 1)
        open.call(ctx, entry)
        expect((Entries as any).methods.entryPropertyValue.call(ctx, 'erledigt')).toBeNull()
        expect(ctx.editDialogOpen).toBe(true)
    })

    it('saves only explicitly selected property classifications with the entry', async () => {
        const methods = (Entries as any).methods
        const saved = entryFixture({ properties_mode: 'free', fixed_properties: ['+', '-', 'gefehlt'] })
        const put = vi.fn().mockResolvedValue({ data: { data: saved } })
        vi.stubGlobal('axios', { put })
        const ctx: any = { ...methods, entries: [], canSaveEntry: true, notifyError: vi.fn() }
        methods.openEditDialog.call(ctx, saved)
        methods.setEntryPropertyValue.call(ctx, '+', 1)
        methods.setEntryPropertyValue.call(ctx, '-', null)
        methods.setEntryPropertyValue.call(ctx, 'gefehlt', 0)
        try {
            await methods.saveEntry.call(ctx)
            expect(put).toHaveBeenCalledWith('/api/admin/teaching/entry_definitions/1', expect.objectContaining({
                property_evaluations: [{ property: '+', evaluation: 1 }, { property: 'gefehlt', evaluation: 0 }],
            }))
            expect(ctx.entries).toEqual([saved])
            expect(ctx.editDialogOpen).toBe(false)
        } finally {
            vi.unstubAllGlobals()
        }
    })


    it('loads saved semester settings for the selected area and keeps drafts separate', () => {
        const ctx: any = {
            activeAreaId: 10,
            areas: [{ id: 10, semester_count: 2, semester_1_weight: 40, semester_2_weight: 60 }, { id: 20 }],
            semesterDrafts: {},
        }
        const form = (Entries as any).computed.semesterForm
        ctx.semesterForm = form.call(ctx)
        expect(ctx.semesterForm).toEqual({ semester_count: 2, semester_1_weight: 40, semester_2_weight: 60 })
        ;(Entries as any).methods.updateSemesterField.call(ctx, 'semester_2_weight', '30')
        expect(form.call(ctx).semester_2_weight).toBe('30')
        expect(form.call(ctx).semester_1_weight).toBe(70)
        ctx.activeAreaId = 20
        expect(form.call(ctx)).toEqual({ semester_count: 1, semester_1_weight: 50, semester_2_weight: 50 })
    })

    it.each([
        ['0', 100], ['100', 0], ['40', 60], ['', ''], [null, ''], ['101', ''], ['-1', ''], ['40.5', ''],
    ])('automatically complements the second semester percentage %s', (value, expected) => {
        const ctx: any = {
            activeAreaId: 10,
            semesterDrafts: {},
            semesterForm: { semester_count: 2, semester_1_weight: 50, semester_2_weight: 50 },
        }
        ;(Entries as any).methods.updateSemesterField.call(ctx, 'semester_2_weight', value)
        expect(ctx.semesterDrafts[10].semester_1_weight).toBe(expected)
    })

    it.each([
        [2, 40, 60, true],
        [2, 0, 100, true],
        [2, 100, 0, true],
        [2, 30, 60, false],
        [2, '', 100, false],
        [2, -1, 101, false],
        [2, 40.5, 59.5, false],
        [1, '', '', true],
    ])('validates semester count %s with weights %s/%s', (count, first, second, valid) => {
        const message = (Entries as any).computed.semesterValidationMessage.call({
            semesterForm: { semester_count: count, semester_1_weight: first, semester_2_weight: second },
        })
        expect(message === '').toBe(valid)
    })

    it.each([1, 2])('saves %s semester configuration for its original area', async (count) => {
        const saved = { id: 10, name: 'Unterstufe', semester_count: count, semester_1_weight: count === 1 ? 100 : 40, semester_2_weight: count === 1 ? 0 : 60 }
        const put = vi.fn().mockResolvedValue({ data: { data: saved } })
        vi.stubGlobal('axios', { put })
        const ctx: any = {
            activeAreaId: 10,
            areas: [{ id: 10, name: 'Unterstufe' }],
            semesterForm: { semester_count: count, semester_1_weight: '40', semester_2_weight: '60' },
            semesterDrafts: { 10: {} },
            semesterValidationMessage: '',
            isSavingSemesters: false,
            notifyError: vi.fn(),
        }
        try {
            await (Entries as any).methods.saveSemesterSettings.call(ctx)
            expect(put).toHaveBeenCalledWith('/api/admin/teaching/entry_areas/10', {
                name: saved.name,
                semester_count: count,
                semester_1_weight: saved.semester_1_weight,
                semester_2_weight: saved.semester_2_weight,
            })
            expect(ctx.areas[0]).toEqual(saved)
            expect(ctx.semesterDrafts[10]).toBeUndefined()
            expect(ctx.isSavingSemesters).toBe(false)
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('retains semester inputs when saving fails', async () => {
        const error = new Error('Save failed')
        vi.stubGlobal('axios', { put: vi.fn().mockRejectedValue(error) })
        const draft = { semester_count: 2, semester_1_weight: 40, semester_2_weight: 60 }
        const ctx: any = {
            activeAreaId: 10,
            areas: [{ id: 10, name: 'Unterstufe' }],
            semesterForm: draft,
            semesterDrafts: { 10: draft },
            semesterValidationMessage: '',
            isSavingSemesters: false,
            notifyError: vi.fn(),
        }
        try {
            await (Entries as any).methods.saveSemesterSettings.call(ctx)
            expect(ctx.semesterDrafts[10]).toEqual(draft)
            expect(ctx.notifyError).toHaveBeenCalledWith(error)
            expect(ctx.isSavingSemesters).toBe(false)
        } finally {
            vi.unstubAllGlobals()
        }
    })

    it('filters entries by area and category', () => {
        const ctx = {
            activeAreaId: 20,
            activeCategory: 'Verhalten',
            entries: [entryFixture(), entryFixture({ id: 2, teaching_entry_area_id: 20, category: 'Verhalten' })],
        }

        expect((Entries as any).computed.filteredEntries.call(ctx).map((entry: any) => entry.id)).toEqual([2])
    })

    it('sorts overview entries by short name without changing the source order', () => {
        const entries = [
            entryFixture({ id: 1, short_name: 'Z', name: 'Erste Mitarbeit' }),
            entryFixture({ id: 2, short_name: 'A', name: 'Zweite Mitarbeit' }),
            entryFixture({ id: 3, category: 'Verhalten', name: 'Andere Kategorie' }),
        ]
        const ctx = {
            activeAreaId: 10,
            activeCategory: 'Benotung',
            entries,
        }

        expect((Entries as any).computed.filteredEntries.call(ctx).map((entry: any) => entry.id)).toEqual([2, 1])
        expect(entries.map((entry) => entry.id)).toEqual([1, 2, 3])
    })

    it('restores the selected entry category from the URL', () => {
        const methods = (Entries as any).methods
        const replace = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            activeCategory: 'Benotung',
            categoryOptions: ['Benotung', 'Berechnung', 'Verhalten', 'Weitere'],
            $route: { query: { panel: 'entries', entry_category: 'Verhalten' } },
            $router: { replace },
            normalizeCategoryQuery: methods.normalizeCategoryQuery,
            isEntriesPanelActive: methods.isEntriesPanelActive,
            syncCategoryQuery: methods.syncCategoryQuery,
        }

        methods.restoreCategoryFromRoute.call(ctx)

        expect(ctx.activeCategory).toBe('Verhalten')
        expect(replace).not.toHaveBeenCalled()
    })

    it('writes the selected entry category to the URL and preserves the settings panel', () => {
        const methods = (Entries as any).methods
        const replace = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            categoryOptions: ['Benotung', 'Berechnung', 'Verhalten', 'Weitere'],
            $route: { query: { panel: 'entries', existing: 'value' } },
            $router: { replace },
            normalizeCategoryQuery: methods.normalizeCategoryQuery,
            isEntriesPanelActive: methods.isEntriesPanelActive,
        }

        methods.syncCategoryQuery.call(ctx, 'Weitere')

        expect(replace).toHaveBeenCalledWith({
            query: {
                panel: 'entries',
                existing: 'value',
                entry_category: 'Weitere',
            },
        })
    })

    it('replaces an invalid entry category with Benotung', () => {
        const methods = (Entries as any).methods
        const replace = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            activeCategory: 'Weitere',
            categoryOptions: ['Benotung', 'Berechnung', 'Verhalten', 'Weitere'],
            $route: { query: { panel: 'entries', entry_category: 'Unbekannt' } },
            $router: { replace },
            normalizeCategoryQuery: methods.normalizeCategoryQuery,
            isEntriesPanelActive: methods.isEntriesPanelActive,
            syncCategoryQuery: methods.syncCategoryQuery,
        }

        methods.restoreCategoryFromRoute.call(ctx)

        expect(ctx.activeCategory).toBe('Benotung')
        expect(replace).toHaveBeenCalledWith({
            query: { panel: 'entries', entry_category: 'Benotung' },
        })
    })

    it('does not restore the entry category after leaving the entries panel', () => {
        const methods = (Entries as any).methods
        const replace = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            activeCategory: 'Benotung',
            categoryOptions: ['Benotung', 'Berechnung', 'Verhalten', 'Weitere'],
            $route: { query: { panel: 'basic' } },
            $router: { replace },
            normalizeCategoryQuery: methods.normalizeCategoryQuery,
            isEntriesPanelActive: methods.isEntriesPanelActive,
            syncCategoryQuery: methods.syncCategoryQuery,
        }

        methods.restoreCategoryFromRoute.call(ctx)

        expect(replace).not.toHaveBeenCalled()
    })

    it('restores the current category when returning to the entries panel', () => {
        const methods = (Entries as any).methods
        const restoreCategoryFromRoute = vi.fn()
        const ctx: any = {
            activeCategory: 'Berechnung',
            $route: { query: { panel: 'entries' } },
            isEntriesPanelActive: methods.isEntriesPanelActive,
            restoreCategoryFromRoute,
        }

        ;(Entries as any).watch['$route.query.panel'].call(ctx, 'entries')

        expect(restoreCategoryFromRoute).toHaveBeenCalledWith('Berechnung')
    })

    it('shows calculation entries only for the selected area', () => {
        const ctx = {
            activeAreaId: 20,
            entries: [
                entryFixture(),
                entryFixture({ id: 2, category: 'Verhalten' }),
                entryFixture({ id: 3, teaching_entry_area_id: 20 }),
            ],
        }

        expect((Entries as any).computed.calculationEntries.call(ctx)).toEqual([expect.objectContaining({ id: 3 })])
    })

    it('shows all grading entries and their properties independently from grading parts', () => {
        const entries = [
            entryFixture({ id: 3, teaching_entry_area_id: 20, fixed_properties: ['+', '++', '-'] }),
            entryFixture({ id: 4, teaching_entry_area_id: 20, properties_mode: 'free', fixed_properties: [] }),
            entryFixture({ id: 5, teaching_entry_area_id: 20, category: 'Verhalten' }),
        ]
        const ctx = {
            activeAreaId: 20,
            entries,
        }

        expect((Entries as any).computed.calculationEntries.call(ctx)).toEqual([entries[0], entries[1]])
    })

    it('renders assigned grading entries in their grading part card', () => {
        const assignedEntry = entryFixture({
            id: 3,
            teaching_entry_area_id: 20,
            teaching_entry_grading_part_id: 100,
        })
        const ctx = {
            activeAreaId: 20,
            calculationEntries: [assignedEntry, entryFixture({ id: 4, teaching_entry_area_id: 20 })],
            gradingParts: [
                { id: 100, teaching_entry_area_id: 20, name: 'Mündlich' },
                { id: 200, teaching_entry_area_id: 10, name: 'Schriftlich' },
            ],
        }

        expect((Entries as any).computed.calculationAreas.call(ctx)).toEqual([{
            id: 'grading-part-100',
            gradingPartId: 100,
            teaching_entry_area_id: 20,
            name: 'Mündlich',
            entries: [assignedEntry],
        }])
    })

    it('preselects the entries already assigned to the selected grading part', () => {
        const methods = (Entries as any).methods
        const ctx: any = {
            calculationEntries: [
                entryFixture({ id: 1, short_name: 'M', name: 'Mitarbeit' }),
                entryFixture({ id: 2, short_name: 'S', name: 'Schularbeit', teaching_entry_grading_part_id: 100 }),
                entryFixture({ id: 3, short_name: 'P', name: 'Prüfung', teaching_entry_grading_part_id: 200 }),
            ],
            assignGradingPartId: null,
            selectedGradingEntryIds: [],
        }

        methods.toggleGradingEntryAssignment.call(ctx, { gradingPartId: 100 })

        expect(ctx.selectedGradingEntryIds).toEqual([2])
    })

    it('shows the possible values for every calculation entry type', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')

        expect(source).not.toContain('Mögliche Werte:')
        expect(source).toContain('v-for="entry in calculationEntries"')
        expect(source).toContain('Benotungsteil hinzufügen')
        expect(source).toContain('@click="openCreateGradingPartDialog"')
        expect(source).toContain('v-for="property in entry.fixed_properties"')
        expect(source).toContain("entry.has_properties && entry.properties_mode !== 'fixed'")
        expect(source).toContain('Freie Eingabe')
        expect(source).toContain('Keine zusätzlichen Werte')
    })

    it('opens a blank entry in the selected area', () => {
        const methods = (Entries as any).methods
        expect((Entries as any).data().entryForm.description).toBe('')

        const ctx: any = {
            areas: [{ id: 10, name: 'Unterstufe' }],
            activeAreaId: 10,
            activeCategory: 'Weitere',
            selectedEntryId: 8,
            entryForm: null,
            formErrors: {},
            editDialogOpen: false,
            createEmptyEntry: methods.createEmptyEntry,
        }

        methods.openCreateDialog.call(ctx)

        expect(ctx.entryForm.teaching_entry_area_id).toBe(10)
        expect(ctx.entryForm.category).toBe('Weitere')
        expect(ctx.entryForm.description).toBe('')
        expect(ctx.entryForm.has_table_marking).toBe(false)
        expect(ctx.entryForm.table_marking_color).toBeNull()
        expect(ctx.editDialogOpen).toBe(true)
    })

    it('loads an existing description when editing an entry', () => {
        const methods = (Entries as any).methods
        const ctx: any = {
            selectedEntryId: null,
            entryForm: null,
            formErrors: { description: ['Veraltet'] },
            editDialogOpen: false,
        }

        methods.openEditDialog.call(ctx, entryFixture({ description: 'Hinweise zur Verwendung' }))

        expect(ctx.selectedEntryId).toBe(1)
        expect(ctx.entryForm.description).toBe('Hinweise zur Verwendung')
        expect(ctx.formErrors).toEqual({})
        expect(ctx.editDialogOpen).toBe(true)
    })

    it('shows an entry description below its title in the overview', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')
        const titleIndex = source.indexOf('<span class="entry-name"')
        const descriptionIndex = source.indexOf('class="entry-description"')

        expect(titleIndex).toBeGreaterThan(-1)
        expect(descriptionIndex).toBeGreaterThan(titleIndex)
        expect(source).toContain('{{ formatEntryDescription(entry.description) }}')
        expect(source).toContain('font-size: 0.78rem')
        expect(source).toContain('white-space: pre-line')
    })

    it('renders line feeds and br markers as description line breaks', () => {
        const formatEntryDescription = (Entries as any).methods.formatEntryDescription

        expect(formatEntryDescription('Erste Zeile\nZweite Zeile')).toBe('Erste Zeile\nZweite Zeile')
        expect(formatEntryDescription('Erste Zeile<br>Zweite Zeile<BR />Dritte Zeile')).toBe(
            'Erste Zeile\nZweite Zeile\nDritte Zeile',
        )
    })

    it('saves notifications instead of properties for behaviour and other entries', async () => {
        const methods = (Entries as any).methods
        const post = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { id: 2, ...payload } } }))
        ;(globalThis as any).axios = { post }

        for (const category of ['Verhalten', 'Weitere']) {
            const ctx: any = {
                areas: [{ id: 10, name: 'Unterstufe' }],
                entries: [],
                entryForm: entryFixture({
                    category,
                    has_notifications: true,
                    notification_recipients: ['class_teacher', 'parents'],
                    has_table_marking: false,
                    table_marking_color: null,
                }),
                selectedEntryId: null,
                activeAreaId: 10,
                activeCategory: category,
                canSaveEntry: true,
                formErrors: {},
                isSaving: false,
                normalizeShortName: methods.normalizeShortName,
                closeEditDialog: methods.closeEditDialog,
                notifyError: vi.fn(),
            }

            await methods.saveEntry.call(ctx)

            expect(post).toHaveBeenLastCalledWith(
                '/api/admin/teaching/entry_definitions',
                expect.objectContaining({
                    category,
                    has_properties: false,
                    properties_mode: 'free',
                    fixed_properties: [],
                    has_notifications: true,
                    notification_recipients: ['class_teacher', 'parents'],
                }),
            )
        }
    })

    it('saves table marking and clears stale notification recipients for grading entries', async () => {
        const methods = (Entries as any).methods
        const post = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { id: 2, ...payload } } }))
        const syncEntryDefinition = vi.fn()
        ;(globalThis as any).axios = { post }
        const ctx: any = {
            areas: [{ id: 10, name: 'Unterstufe' }],
            entries: [],
            entryForm: entryFixture({
                description: '  Für Wiederholungen.  ',
                has_notifications: true,
                notification_recipients: ['student'],
                has_table_marking: true,
                table_marking_color: 'purple',
            }),
            selectedEntryId: null,
            activeAreaId: 10,
            activeCategory: 'Benotung',
            canSaveEntry: true,
            formErrors: {},
            isSaving: false,
            normalizeShortName: methods.normalizeShortName,
            closeEditDialog: methods.closeEditDialog,
            notifyError: vi.fn(),
            courseStore: { syncEntryDefinition },
        }

        await methods.saveEntry.call(ctx)

        expect(post).toHaveBeenCalledWith(
            '/api/admin/teaching/entry_definitions',
            expect.objectContaining({
                has_notifications: false,
                notification_recipients: [],
                has_table_marking: true,
                table_marking_color: 'purple',
                description: 'Für Wiederholungen.',
            }),
        )
        expect(syncEntryDefinition).toHaveBeenCalledWith(expect.objectContaining({
            table_marking_color: 'purple',
        }))
    })

    it('requires a configured color when table marking is enabled', () => {
        const canSaveEntry = (Entries as any).computed.canSaveEntry
        const ctx: any = {
            areas: [{ id: 10, name: 'Unterstufe' }],
            entryForm: entryFixture({ teaching_entry_area_id: 10, has_table_marking: true, table_marking_color: null }),
        }

        expect(canSaveEntry.call(ctx)).toBe(false)

        ctx.entryForm.table_marking_color = 'green'
        expect(canSaveEntry.call(ctx)).toBe(true)
    })

    it('offers exactly five table marking colors', () => {
        expect((Entries as any).data().tableMarkingColors).toEqual([
            expect.objectContaining({ value: 'blue', label: 'Blau' }),
            expect.objectContaining({ value: 'green', label: 'Grün' }),
            expect.objectContaining({ value: 'orange', label: 'Orange' }),
            expect.objectContaining({ value: 'purple', label: 'Violett' }),
            expect.objectContaining({ value: 'red', label: 'Rot' }),
        ])
    })

    it('creates and renames areas through their API', async () => {
        const methods = (Entries as any).methods
        const initialGradingPart = { id: 20, teaching_entry_area_id: 10, name: 'Unterstufe' }
        const post = vi.fn().mockResolvedValue({
            data: { data: { id: 10, name: 'Unterstufe', entry_count: 0 }, grading_part: initialGradingPart },
        })
        const put = vi.fn().mockResolvedValue({ data: { data: { id: 10, name: 'Mittelstufe', entry_count: 0 } } })
        ;(globalThis as any).axios = { post, put }
        const ctx: any = {
            areas: [],
            gradingParts: [],
            activeAreaId: null,
            editingAreaId: null,
            areaForm: { name: '  Unterstufe  ' },
            areaFormErrors: {},
            areaDialogOpen: true,
            isSavingArea: false,
            closeAreaDialog: methods.closeAreaDialog,
            notifyError: vi.fn(),
        }

        await methods.saveArea.call(ctx)
        expect(ctx.areas[0].name).toBe('Unterstufe')
        expect(ctx.gradingParts).toEqual([initialGradingPart])
        expect(post).toHaveBeenCalledWith('/api/admin/teaching/entry_areas', { name: 'Unterstufe' })

        ctx.editingAreaId = 10
        ctx.areaForm = { name: 'Mittelstufe' }
        await methods.saveArea.call(ctx)
        expect(ctx.areas[0].name).toBe('Mittelstufe')
        expect(put).toHaveBeenCalledWith('/api/admin/teaching/entry_areas/10', { name: 'Mittelstufe' })
    })

    it('keeps the selected area visible after adding a grading part', async () => {
        const methods = (Entries as any).methods
        const post = vi.fn().mockResolvedValue({ data: { data: { id: 20, teaching_entry_area_id: 10, name: 'Mündlich' } } })
        ;(globalThis as any).axios = { post }
        const ctx: any = {
            gradingParts: [],
            activeAreaId: 10,
            gradingPartForm: { name: 'Mündlich', weight: 6, is_required: false },
            gradingPartWeightValid: true,
            gradingPartFormErrors: {},
            gradingPartDialogOpen: true,
            isSavingGradingPart: false,
            closeGradingPartDialog: methods.closeGradingPartDialog,
            notifyError: vi.fn(),
        }

        await methods.saveGradingPart.call(ctx)

        expect(ctx.activeAreaId).toBe(10)
        expect(ctx.gradingParts).toContainEqual(expect.objectContaining({ id: 20, name: 'Mündlich' }))
        expect(post).toHaveBeenCalledWith('/api/admin/teaching/entry_grading_parts', {
            teaching_entry_area_id: 10,
            allowed_entry_types: 'all',
            name: 'Mündlich',
            weight: 6,
            is_required: false,
            fixed_percentage: null,
        })
    })

    it('prefills and updates an existing grading part', async () => {
        const methods = (Entries as any).methods
        const updatedGradingPart = { id: 20, teaching_entry_area_id: 10, name: 'Mitarbeit', weight: 2.5, is_required: false }
        const put = vi.fn().mockResolvedValue({ data: { data: updatedGradingPart } })
        ;(globalThis as any).axios = { put }
        const ctx: any = {
            gradingParts: [{ id: 20, teaching_entry_area_id: 10, name: 'Mündlich' }],
            activeAreaId: 10,
            editingGradingPartId: null,
            gradingPartForm: { name: '' },
            gradingPartWeightValid: true,
            gradingPartFormErrors: {},
            gradingPartDialogOpen: false,
            isSavingGradingPart: false,
            closeGradingPartDialog: methods.closeGradingPartDialog,
            notifyError: vi.fn(),
        }

        methods.openEditGradingPartDialog.call(ctx, {
            gradingPartId: 20,
            name: 'Mündlich',
            weight: 6,
            is_required: true,
        })

        expect(ctx.editingGradingPartId).toBe(20)
        expect(ctx.gradingPartForm.name).toBe('Mündlich')
        expect(ctx.gradingPartForm.weight).toBe(6)
        expect(ctx.gradingPartForm.is_required).toBe(true)
        expect(ctx.gradingPartDialogOpen).toBe(true)

        ctx.gradingPartForm.name = 'Mitarbeit'
        ctx.gradingPartForm.weight = '2.5'
        ctx.gradingPartForm.is_required = false
        await methods.saveGradingPart.call(ctx)

        expect(put).toHaveBeenCalledWith('/api/admin/teaching/entry_grading_parts/20', {
            name: 'Mitarbeit',
            weight: 2.5,
            is_required: false,
            fixed_percentage: null,
            allowed_entry_types: 'all',
        })
        expect(ctx.gradingParts).toEqual([updatedGradingPart])
        expect(ctx.editingGradingPartId).toBeNull()
    })

    it.each([['6', true], ['2.5', true], ['0.001', true], ['', false], [null, false], ['0', false], ['-1', false], ['1.0001', false], ['Infinity', false], ['10000000', false]])('validates grading weight %s', (weight, valid) => {
        expect((Entries as any).computed.gradingPartWeightValid.call({ gradingPartForm: { weight } })).toBe(valid)
    })

    it('starts new and legacy grading parts with equal weight', () => {
        const methods = (Entries as any).methods
        const ctx: any = { activeAreaId: 10 }
        methods.openCreateGradingPartDialog.call(ctx)
        expect(ctx.gradingPartForm.weight).toBe(1)
        expect(ctx.gradingPartForm.is_required).toBe(false)
        methods.openEditGradingPartDialog.call(ctx, { gradingPartId: 20, name: 'Mitarbeit' })
        expect(ctx.gradingPartForm.weight).toBe(1)
        expect(ctx.gradingPartForm.is_required).toBe(false)
    })

    it.each(['all', 'points'])('saves and restores allowed entry types %s', async (allowed) => {
        const methods = (Entries as any).methods
        const ctx: any = { activeAreaId: 10, gradingParts: [], gradingPartWeightValid: true,
            closeGradingPartDialog: methods.closeGradingPartDialog, notifyError: vi.fn() }
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { id: 20, ...payload } } }))
        vi.stubGlobal('axios', { put })
        try {
            methods.openCreateGradingPartDialog.call(ctx)
            expect(ctx.gradingPartForm.allowed_entry_types).toBe('all')
            methods.openEditGradingPartDialog.call(ctx, { gradingPartId: 20, name: 'Prüfung' })
            expect(ctx.gradingPartForm.allowed_entry_types).toBe('all')
            ctx.gradingPartForm.allowed_entry_types = allowed
            await methods.saveGradingPart.call(ctx)
            expect(put).toHaveBeenCalledWith(expect.any(String), expect.objectContaining({ allowed_entry_types: allowed }))
            methods.openEditGradingPartDialog.call(ctx, { ...ctx.gradingParts[0], gradingPartId: 20 })
            expect(ctx.gradingPartForm.allowed_entry_types).toBe(allowed)
        } finally { vi.unstubAllGlobals() }
    })

    it.each(['overall', 'individual'])('saves point assessment %s, restores it and excludes it for unrestricted parts', async (mode) => {
        const methods = (Entries as any).methods
        const ctx: any = { activeAreaId: 10, gradingParts: [], gradingPartWeightValid: true,
            closeGradingPartDialog: methods.closeGradingPartDialog, notifyError: vi.fn() }
        const put = vi.fn().mockImplementation((_url, payload) => Promise.resolve({ data: { data: { id: 20, ...payload } } }))
        vi.stubGlobal('axios', { put })
        try {
            methods.openCreateGradingPartDialog.call(ctx)
            expect(ctx.gradingPartForm.points_assessment_mode).toBe('individual')
            methods.openEditGradingPartDialog.call(ctx, { gradingPartId: 20, name: 'Prüfung', allowed_entry_types: 'points' })
            expect(ctx.gradingPartForm.points_assessment_mode).toBe('individual')
            ctx.gradingPartForm.points_assessment_mode = mode
            await methods.saveGradingPart.call(ctx)
            expect(put).toHaveBeenLastCalledWith(expect.any(String), expect.objectContaining({ allowed_entry_types: 'points', points_assessment_mode: mode }))
            const saved = { ...ctx.gradingParts[0], gradingPartId: 20 }
            methods.openEditGradingPartDialog.call(ctx, saved)
            expect(ctx.gradingPartForm.points_assessment_mode).toBe(mode)
            ctx.gradingPartForm.points_assessment_mode = mode === 'overall' ? 'individual' : 'overall'
            methods.closeGradingPartDialog.call(ctx)
            methods.openEditGradingPartDialog.call(ctx, saved)
            expect(ctx.gradingPartForm.points_assessment_mode).toBe(mode)
            ctx.gradingPartForm.allowed_entry_types = 'all'
            await methods.saveGradingPart.call(ctx)
            expect(put.mock.calls.at(-1)[1]).not.toHaveProperty('points_assessment_mode')
        } finally { vi.unstubAllGlobals() }
    })

    it('offers point assessment choices only for points-only parts', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')
        expect(source).toContain('<section v-if="gradingPartForm.allowed_entry_types === \'points\'" class="mt-5" aria-label="Beurteilung der Punktetypen">')
        expect(source).toMatch(/<v-btn-toggle\s+v-model="gradingPartForm.points_assessment_mode"\s+class="maximum-plus-grading-options"[\s\S]*?\smandatory\s/)
        expect(source).toContain('<v-btn value="overall">Gesamtbeurteilung</v-btn>')
        expect(source).toContain('<v-btn value="individual">Einzelbeurteilungen</v-btn>')
    })

    it.each(['fixed', 'free', 'plus', 'plus_minus', 'points'])('restricts assignment based on property mode %s', (mode) => {
        const methods = (Entries as any).methods
        const entry = entryFixture({ properties_mode: mode })
        const part = { id: 20, allowed_entry_types: 'points' }
        const ctx: any = { gradingParts: [part], gradingPartPendingAssignment: part, assignGradingPartId: 20, selectedGradingEntryIds: [] }
        expect(methods.canAssignGradingEntry.call(ctx, entry)).toBe(mode === 'points')
        methods.toggleGradingEntrySelection.call(ctx, entry)
        expect(ctx.selectedGradingEntryIds).toEqual(mode === 'points' ? [1] : [])
        expect(methods.canAssignGradingEntry.call({ gradingPartPendingAssignment: { allowed_entry_types: 'all' } }, entry)).toBe(true)
    })

    it('blocks restriction changes when the current part contains other types but ignores other parts', async () => {
        const computed = (Entries as any).computed.gradingPartHasNonPointEntries
        const entries = [entryFixture({ properties_mode: 'points', teaching_entry_grading_part_id: 20 }),
            entryFixture({ id: 2, properties_mode: 'free', teaching_entry_grading_part_id: 30 })]
        expect(computed.call({ editingGradingPartId: 20, calculationEntries: entries })).toBe(false)
        expect(computed.call({ editingGradingPartId: null, calculationEntries: entries })).toBe(false)
        entries[1].teaching_entry_grading_part_id = 20
        expect(computed.call({ editingGradingPartId: 20, calculationEntries: entries })).toBe(true)
        const put = vi.fn()
        vi.stubGlobal('axios', { put })
        try {
            const ctx: any = { activeAreaId: 10, gradingPartForm: { name: 'Prüfung', allowed_entry_types: 'points' },
                gradingPartWeightValid: true, gradingPartHasNonPointEntries: true }
            await (Entries as any).methods.saveGradingPart.call(ctx)
            expect(put).not.toHaveBeenCalled()
            expect(ctx.gradingPartFormErrors.allowed_entry_types[0]).toContain('andere Eintragstypen')
        } finally { vi.unstubAllGlobals() }
    })

    it('rejects incompatible pending assignments before removing any existing assignment', async () => {
        const post = vi.fn()
        const remove = vi.fn()
        vi.stubGlobal('axios', { post, delete: remove })
        try {
            const ctx: any = { assignGradingPartId: 20, selectedGradingEntryIds: [1],
                gradingParts: [{ id: 20, allowed_entry_types: 'points' }],
                calculationEntries: [entryFixture({ properties_mode: 'free', teaching_entry_grading_part_id: 30 })], notifyError: vi.fn() }
            await (Entries as any).methods.saveGradingEntryAssignments.call(ctx)
            expect(post).not.toHaveBeenCalled()
            expect(remove).not.toHaveBeenCalled()
            expect(ctx.notifyError).toHaveBeenCalled()
        } finally { vi.unstubAllGlobals() }
    })

    it('does not submit an invalid grading weight', async () => {
        const post = vi.fn()
        ;(globalThis as any).axios = { post }
        await (Entries as any).methods.saveGradingPart.call({ activeAreaId: 10, gradingPartForm: { name: 'Mitarbeit', weight: '' }, gradingPartWeightValid: false })
        expect(post).not.toHaveBeenCalled()
    })

    it('loads, saves and clears a fixed percentage without replacing the saved relative weight', async () => {
        const methods = (Entries as any).methods
        const put = vi.fn().mockResolvedValue({ data: { data: { id: 20, name: 'Prüfung', weight: 3, fixed_percentage: 30 } } })
        ;(globalThis as any).axios = { put }
        const ctx: any = { activeAreaId: 10, gradingParts: [], gradingPartWeightValid: true, closeGradingPartDialog: vi.fn(), notifyError: vi.fn() }
        methods.openEditGradingPartDialog.call(ctx, { gradingPartId: 20, name: 'Prüfung', weight: 3, fixed_percentage: 30 })
        expect(ctx.gradingPartForm.weighting_mode).toBe('fixed')
        expect(ctx.gradingPartForm.fixed_percentage).toBe(30)
        await methods.saveGradingPart.call(ctx)
        expect(put).toHaveBeenLastCalledWith('/api/admin/teaching/entry_grading_parts/20', { name: 'Prüfung', fixed_percentage: 30, is_required: false, allowed_entry_types: 'all' })
        ctx.gradingPartForm.weighting_mode = 'relative'
        await methods.saveGradingPart.call(ctx)
        expect(put).toHaveBeenLastCalledWith('/api/admin/teaching/entry_grading_parts/20', { name: 'Prüfung', weight: 3, fixed_percentage: null, is_required: false, allowed_entry_types: 'all' })
    })

    it.each([['30', false], ['100', false], ['0.001', false], ['', true], ['0', true], ['101', true], ['1.0001', true]])('validates fixed percentage %s', (fixedPercentage, invalid) => {
        const ctx = { gradingPartForm: { weighting_mode: 'fixed', fixed_percentage: fixedPercentage }, gradingParts: [], activeAreaId: 10 }
        const error = (Entries as any).computed.gradingPartPercentageError.call(ctx)
        expect(Boolean(error)).toBe(invalid)
        expect((Entries as any).computed.gradingPartWeightValid.call({ ...ctx, gradingPartPercentageError: error })).toBe(!invalid)
    })

    it('limits fixed percentages to 100 in the active area and excludes the edited part', () => {
        const ctx: any = { activeAreaId: 10, editingGradingPartId: 20, gradingPartForm: { weighting_mode: 'fixed', fixed_percentage: 30 }, gradingParts: [
            { id: 20, teaching_entry_area_id: 10, fixed_percentage: 50 },
            { id: 21, teaching_entry_area_id: 10, fixed_percentage: 70 },
            { id: 22, teaching_entry_area_id: 11, fixed_percentage: 100 },
        ] }
        expect((Entries as any).computed.gradingPartPercentageError.call(ctx)).toBe('')
        ctx.gradingPartForm.fixed_percentage = 30.001
        expect((Entries as any).computed.gradingPartPercentageError.call(ctx)).toContain('100 %')
    })

    it('distinguishes fixed percentages from weights in the overview', () => {
        const label = (Entries as any).methods.gradingPartWeightLabel
        expect(label({ weight: 3, fixed_percentage: 30 })).toBe('30 % fest')
        expect(label({ weight: 6, fixed_percentage: null })).toBe('Gewicht 6')
    })

    it('deletes a grading part without removing entries', async () => {
        const methods = (Entries as any).methods
        const deleteRequest = vi.fn().mockResolvedValue({})
        ;(globalThis as any).axios = { delete: deleteRequest }
        const entries = [entryFixture()]
        const ctx: any = {
            gradingParts: [{ id: 20, teaching_entry_area_id: 10, name: 'Mündlich' }],
            entries,
            deleteGradingPartId: 20,
            gradingPartDeleteDialogOpen: true,
            isDeletingGradingPart: false,
            closeDeleteGradingPartDialog: methods.closeDeleteGradingPartDialog,
            notifyError: vi.fn(),
        }

        await methods.confirmGradingPartDelete.call(ctx)

        expect(deleteRequest).toHaveBeenCalledWith('/api/admin/teaching/entry_grading_parts/20')
        expect(ctx.gradingParts).toEqual([])
        expect(ctx.entries).toEqual(entries)
    })

    it('loads all grading entries and saves selected and deselected assignments', async () => {
        const methods = (Entries as any).methods
        const entries = [
            entryFixture({ id: 1, name: 'Mitarbeit' }),
            entryFixture({ id: 2, name: 'Prüfung', teaching_entry_grading_part_id: 20 }),
            entryFixture({ id: 3, name: 'Auftrag', teaching_entry_grading_part_id: 30 }),
        ]
        const post = vi.fn().mockImplementation((_url, payload) => Promise.resolve({
            data: {
                data: {
                    ...entries.find((entry) => entry.id === payload.teaching_entry_definition_id),
                    teaching_entry_grading_part_id: 20,
                },
            },
        }))
        const deleteRequest = vi.fn().mockResolvedValue({})
        ;(globalThis as any).axios = { delete: deleteRequest, post }
        const ctx: any = {
            entries,
            calculationEntries: entries,
            gradingParts: [
                { id: 20, name: 'Mündlich' },
                { id: 30, name: 'Schriftlich' },
            ],
            assignGradingPartId: null,
            selectedGradingEntryIds: [],
            isAssigningGradingEntry: false,
            cancelGradingEntryAssignment: methods.cancelGradingEntryAssignment,
            replaceGradingEntry: methods.replaceGradingEntry,
            notifyError: vi.fn(),
        }

        methods.toggleGradingEntryAssignment.call(ctx, { gradingPartId: 20 })
        expect(ctx.selectedGradingEntryIds).toEqual([2])

        methods.toggleGradingEntrySelection.call(ctx, entries[0])
        methods.toggleGradingEntrySelection.call(ctx, entries[1])
        methods.toggleGradingEntrySelection.call(ctx, entries[2])
        expect(ctx.selectedGradingEntryIds).toEqual([1, 3])

        await methods.saveGradingEntryAssignments.call(ctx)

        expect(post).toHaveBeenNthCalledWith(1, '/api/admin/teaching/entry_grading_parts/20/entries', {
            teaching_entry_definition_id: 1,
        })
        expect(post).toHaveBeenNthCalledWith(2, '/api/admin/teaching/entry_grading_parts/20/entries', {
            teaching_entry_definition_id: 3,
        })
        expect(deleteRequest).toHaveBeenNthCalledWith(1, '/api/admin/teaching/entry_grading_parts/20/entries/2')
        expect(deleteRequest).toHaveBeenNthCalledWith(2, '/api/admin/teaching/entry_grading_parts/30/entries/3')
        expect(ctx.entries).toEqual([
            expect.objectContaining({ id: 1, teaching_entry_grading_part_id: 20 }),
            expect.objectContaining({ id: 2, teaching_entry_grading_part_id: null }),
            expect.objectContaining({ id: 3, teaching_entry_grading_part_id: 20 }),
        ])
        expect(ctx.assignGradingPartId).toBeNull()
        expect(ctx.selectedGradingEntryIds).toEqual([])
    })

    it('allows saving an empty selection to remove every assignment', () => {
        const hasChanges = (Entries as any).computed.hasGradingEntryAssignmentChanges
        const ctx: any = {
            assignGradingPartId: 20,
            isAssigningGradingEntry: false,
            calculationEntries: [entryFixture({ teaching_entry_grading_part_id: 20 })],
            selectedGradingEntryIds: [],
        }

        expect(hasChanges.call(ctx)).toBe(true)
    })

    it('removes only empty areas after confirmation', async () => {
        const methods = (Entries as any).methods
        const deleteRequest = vi.fn().mockResolvedValue({})
        ;(globalThis as any).axios = { delete: deleteRequest }
        const ctx: any = {
            areas: [
                { id: 10, name: 'Leer' },
                { id: 20, name: 'Unterstufe' },
            ],
            entries: [entryFixture({ teaching_entry_area_id: 20 })],
            activeAreaId: 10,
            deleteAreaId: null,
            areaDeleteDialogOpen: false,
            isDeletingArea: false,
            entryCountForArea: methods.entryCountForArea,
            closeAreaDeleteDialog: methods.closeAreaDeleteDialog,
            notifyError: vi.fn(),
        }

        methods.openDeleteAreaDialog.call(ctx, ctx.areas[0])
        await methods.confirmAreaDelete.call(ctx)

        expect(ctx.areas.map((area: any) => area.id)).toEqual([20])
        expect(deleteRequest).toHaveBeenCalledWith('/api/admin/teaching/entry_areas/10')
    })

    it('copies every returned entry from a source area into the active area', async () => {
        const methods = (Entries as any).methods
        const copiedEntries = [entryFixture({ id: 20, teaching_entry_area_id: 10 }), entryFixture({ id: 21, teaching_entry_area_id: 10, short_name: 'A' })]
        const post = vi.fn().mockResolvedValue({ data: { data: copiedEntries, copied_count: 2 } })
        ;(globalThis as any).axios = { post }
        const ctx: any = {
            entries: [entryFixture({ teaching_entry_area_id: 20 })],
            activeAreaId: 10,
            selectedSourceAreaId: 20,
            entryCopyDialogOpen: true,
            entryCopyErrors: {},
            isCopyingEntries: false,
            closeEntryCopyDialog: methods.closeEntryCopyDialog,
            notifyError: vi.fn(),
            notifySuccess: vi.fn(),
        }

        await methods.copyEntriesFromArea.call(ctx)

        expect(post).toHaveBeenCalledWith('/api/admin/teaching/entry_areas/10/entry-copies', { source_area_id: 20 })
        expect(ctx.entries.slice(-2)).toEqual(copiedEntries)
        expect(ctx.entryCopyDialogOpen).toBe(false)
        expect(ctx.notifySuccess).toHaveBeenCalledWith('2 Einträge wurden übernommen.')
    })

    it('loads the previous schoolyear offer without opening the dialog', async () => {
        const methods = (Entries as any).methods
        const previousYearImport = {
            schoolyear: { id: 5, label: '2025/26' },
            area_count: 2,
            entry_count: 7,
        }
        const get = vi.fn().mockImplementation((url: string) => {
            if (url === '/api/admin/teaching/entry_areas') {
                return Promise.resolve({ data: { data: [], meta: { previous_year_import: previousYearImport } } })
            }

            return Promise.resolve({ data: { data: [] } })
        })
        ;(globalThis as any).axios = { get }
        const ctx: any = {
            areas: [],
            entries: [],
            gradingParts: [],
            activeAreaId: null,
            isLoading: false,
            previousYearImportOffer: null,
            previousYearImportDialogOpen: false,
            notifyError: vi.fn(),
        }

        await methods.loadData.call(ctx)

        expect(ctx.previousYearImportOffer).toEqual(previousYearImport)
        expect(ctx.previousYearImportDialogOpen).toBe(false)

        methods.openPreviousYearImport.call(ctx)
        expect(ctx.previousYearImportDialogOpen).toBe(true)
    })

    it('imports previous schoolyear areas and entries into the local view', async () => {
        const methods = (Entries as any).methods
        const areas = [{ id: 30, name: 'Unterstufe', entry_count: 1 }]
        const entries = [entryFixture({ id: 40, teaching_entry_area_id: 30 })]
        const gradingParts = [{ id: 50, teaching_entry_area_id: 30, name: 'Unterstufe' }]
        const post = vi.fn().mockResolvedValue({
            data: {
                data: { areas, entries, grading_parts: gradingParts },
                imported_area_count: 1,
                imported_entry_count: 1,
            },
        })
        ;(globalThis as any).axios = { post }
        const ctx: any = {
            areas: [],
            entries: [],
            gradingParts: [],
            activeAreaId: null,
            previousYearImportOffer: { schoolyear: { id: 5, label: '2025/26' } },
            previousYearImportDialogOpen: true,
            isImportingPreviousYear: false,
            notifyError: vi.fn(),
            notifySuccess: vi.fn(),
        }

        await methods.importPreviousYearAreas.call(ctx)

        expect(post).toHaveBeenCalledWith('/api/admin/teaching/entry-area-imports')
        expect(ctx.areas).toEqual(areas)
        expect(ctx.entries).toEqual(entries)
        expect(ctx.gradingParts).toEqual(gradingParts)
        expect(ctx.activeAreaId).toBe(30)
        expect(ctx.previousYearImportDialogOpen).toBe(false)
        expect(ctx.notifySuccess).toHaveBeenCalledWith('1 Bereich und 1 Eintrag wurden übernommen.')
    })

    it('renders all area cards in a wrapping grid and persistent CRUD dialogs without dropdowns', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/settings/components/Entries.vue'), 'utf8')
        const areasTitleIndex = source.indexOf(':title="assignGradingPartId ? \'Zuordnung\' : \'Bereiche\'"')
        const selectedAreaTitleIndex = source.indexOf('<div class="text-h6 font-weight-bold">{{ activeAreaName }}</div>')
        const entryListIndex = source.indexOf('<v-list class="bg-transparent pa-0 mt-2">')
        const addEntryButtonIndex = source.indexOf('@click="openCreateDialog"')
        const designationCardIndex = source.indexOf('<strong>Bezeichnung</strong>')
        const descriptionFieldIndex = source.indexOf('v-model="entryForm.description"')
        const tableMarkingCardIndex = source.indexOf('<strong>Markierung in Tabelle</strong>')
        const propertiesCardIndex = source.indexOf('<strong>Eigenschaften</strong>')
        const calculationPageIndex = source.indexOf('<template v-if="activeCategory === \'Berechnung\'">')
        const semesterGradeTitleIndex = source.indexOf('<div class="text-h6 font-weight-bold">Semesternote</div>')
        const calculationAreaListIndex = source.indexOf('class="calculation-area-list mt-3"')
        const addGradingPartButtonIndex = source.indexOf('Benotungsteil hinzufügen')
        const assignGradingPartButtonIndex = source.indexOf('@click="toggleGradingEntryAssignment(area)"')
        const editGradingPartButtonIndex = source.indexOf('@click="openEditGradingPartDialog(area)"')
        const deleteGradingPartButtonIndex = source.indexOf('@click="openDeleteGradingPartDialog(area)"')

        expect(areasTitleIndex).toBeGreaterThanOrEqual(0)
        expect(selectedAreaTitleIndex).toBeGreaterThan(areasTitleIndex)
        expect(addEntryButtonIndex).toBeGreaterThan(entryListIndex)
        expect(descriptionFieldIndex).toBeGreaterThan(designationCardIndex)
        expect(tableMarkingCardIndex).toBeGreaterThan(descriptionFieldIndex)
        expect(propertiesCardIndex).toBeGreaterThan(tableMarkingCardIndex)
        expect(semesterGradeTitleIndex).toBeGreaterThan(calculationPageIndex)
        expect(addGradingPartButtonIndex).toBeGreaterThan(calculationAreaListIndex)
        expect(editGradingPartButtonIndex).toBeGreaterThan(assignGradingPartButtonIndex)
        expect(deleteGradingPartButtonIndex).toBeGreaterThan(editGradingPartButtonIndex)
        expect(source).toContain('label="Beschreibung"')
        expect(source).toContain(':error-messages="formErrors.description"')
        expect(source).not.toContain('Aktiver Bereich: {{ activeAreaName }}')
        expect(source).toContain('class="entry-list-actions mt-4"')
        expect(source).toContain('@click="openEntryCopyDialog"')
        expect(source).toContain('v-if="entryCountForArea(activeAreaId) === 0"')
        expect(source).toContain('v-model="entryCopyDialogOpen"')
        expect(source).toContain('Einträge übernehmen')
        expect(source).toContain('Quellbereich auswählen')
        expect(source).toContain('@click="copyEntriesFromArea"')
        expect(source).toContain('class="entry-area-grid mt-4"')
        expect(source).toContain("categoryOptions: ['Benotung', 'Berechnung', 'Verhalten', 'Weitere']")
        expect(source).toContain('v-for="area in calculationAreas"')
        expect(source).toContain('v-for="entry in area.entries"')
        expect(source).toContain('class="calculation-area-list mt-3"')
        expect(source).toContain('class="calculation-entry-selection-grid"')
        expect(source).toContain('<section v-if="assignGradingPartId" class="calculation-area-card mt-3">')
        expect(source).toContain('v-for="entry in calculationEntries"')
        expect(source).toContain('class="calculation-entry-selection-card"')
        expect(source).toContain("'calculation-entry-selection-card--selected': selectedGradingEntryIds.includes(entry.id)")
        expect(source).toContain(':aria-pressed="selectedGradingEntryIds.includes(entry.id)"')
        expect(source).toContain('grid-template-columns: repeat(auto-fit, minmax(180px, 1fr))')
        expect(source).toContain('@click="toggleGradingEntryAssignment(area)"')
        expect(source).toContain('title="Benotungsteil bearbeiten"')
        expect(source).toContain('@click="openEditGradingPartDialog(area)"')
        expect(source).toContain('@click="toggleGradingEntrySelection(entry)"')
        expect(source).toContain('@click="saveGradingEntryAssignments"')
        expect(source).toContain('Zuordnung speichern')
        expect(source).toContain("{{ assignGradingPartId === area.gradingPartId ? 'Auswahl abbrechen' : 'Zuordnung' }}")
        expect(source).not.toContain('@click="assignGradingEntry(entry)"')
        expect(source).not.toContain('calculation-entry-list--source')
        expect(source).not.toContain('v-model="assignGradingEntryDialogOpen"')
        expect(source).not.toContain('v-model="selectedGradingEntryId"')
        expect(source).not.toContain('mdi-link-off')
        expect(source).not.toContain('@click="unassignGradingEntry(area, entry)"')
        expect(source).toContain('title="Benotungsteil löschen"')
        expect(source).toContain('class="calculation-area-card calculation-part-card"')
        expect(source).toContain('@click="openDeleteGradingPartDialog(area)"')
        expect(source).toContain('@click="confirmGradingPartDelete"')
        expect(source).toContain('<template v-if="activeCategory !== \'Berechnung\'">')
        expect(source).toContain('<div v-if="activeCategory !== \'Berechnung\'" class="entry-section-actions">')
        expect(source).toContain('grid-template-columns: repeat(auto-fit, minmax(220px, 1fr))')
        expect(source).toContain('class="entry-area-card"')
        expect(source).toContain('@click="activeAreaId = area.id"')
        expect(source).not.toContain('class="entry-area-count"')
        expect(source).not.toContain('<v-slide-group')
        expect(source).toContain('@click="openCreateAreaDialog"')
        expect(source).toContain('class="entry-area-actions"')
        expect(source).toContain('@click="openEditAreaDialog(area)"')
        expect(source).toContain('class="entry-area-action-button entry-area-action-button--edit"')
        expect(source).toContain('class="entry-area-action-button entry-area-action-button--delete"')
        expect(source).toContain('<v-icon icon="mdi-pencil-outline" size="18" />')
        expect(source).toContain('<v-icon icon="mdi-delete-outline" size="18" />')
        expect(source).toContain('<span>Bearbeiten</span>')
        expect(source).toContain('<span>Löschen</span>')
        expect(source).toContain('grid-template-columns: repeat(2, minmax(0, 1fr))')
        expect(source).toContain('margin-top: auto')
        expect(source).toContain('text-transform: none')
        expect(source).toContain('@click="confirmAreaDelete"')
        expect(source).toContain("axios.get('/api/admin/teaching/entry_areas')")
        expect(source).toContain('v-model="previousYearImportDialogOpen"')
        expect(source).toContain('@click="openPreviousYearImport"')
        expect(source).toContain('Aus Vorjahr übernehmen')
        expect(source).toContain('Aus dem Vorjahr übernehmen?')
        expect(source).toContain("axios.post('/api/admin/teaching/entry-area-imports')")
        expect(source).not.toContain('v-model="entryForm.teaching_entry_area_id"')
        expect(source).not.toContain('v-model="entryForm.category"')
        expect(source).not.toContain('categorySelectionOptions')
        expect(source).not.toContain('<strong>Bereich / Oberbegriff</strong>')
        expect(source).not.toContain('<strong>Kategorie</strong>')
        expect(source).toContain('<section v-if="entryForm.category === \'Benotung\'" class="form-section">\n                        <div class="properties-heading">')
        expect(source).toContain('v-model="entryForm.has_table_marking"')
        expect(source).toContain('v-model="entryForm.table_marking_color"')
        expect(source).toContain('<span>Nein</span>')
        expect(source).toContain('<span>Ja</span>')
        expect(source).toContain('<section v-else class="form-section">')
        expect(source).toContain('<strong>Verständigungen</strong>')
        expect(source).toContain('v-model="entryForm.has_notifications"')
        expect(source).toContain('label="Klassenvorstand"')
        expect(source).toContain('label="Eltern"')
        expect(source).toContain('label="Schüler:in"')
        expect(source).toContain('<span class="entry-short">{{ entry.short_name }}</span>')
        expect(source).not.toContain('<v-chip class="entry-short-chip"')
        expect(source).toContain('<div v-if="entry.category === \'Benotung\' && entry.has_properties" class="entry-properties">')
        expect(source).not.toContain('<span>Eigenschaften:</span>')
        expect(source).toContain('v-for="property in entry.fixed_properties"')
        expect(source).toContain('class="entry-property-chip"')
        expect(source).toContain('size="x-small"')
        expect(source).toContain('class="entry-property-type"><span>Eigenschaftstyp</span><strong>{{ entryPropertyTypeLabel(entry) }}</strong>')
        expect(source).toContain('class="entry-property-caption">Werte</span>')
        expect(source).not.toContain('entry-property-chip--free')
        expect(source).toContain('v-model="entryForm.enabled_special_properties"')
        expect(source).toContain('v-for="option in specialPropertyOptions"')
        expect(source).not.toContain('mdi-tag-outline')
        expect(source).toContain('class="entry-properties-combobox mt-4"')
        expect(source).toContain('.entry-properties-combobox :deep(.v-chip)')
        expect(source).toContain('height: 42px !important')
        expect(source).not.toContain('teaching_schema_id')
        expect(source).not.toContain('<v-select')
        expect(source).not.toContain('Feste Eigenschaften')
        expect(source).not.toContain('areaExamples')
        expect(source).not.toContain('area-example-row')
    })
})
