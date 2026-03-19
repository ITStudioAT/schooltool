import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import Overview from '@/pages/admin/aba/components/Overview.vue'

const methods = (Overview as any).methods

describe('ABA overview list actions', () => {
    it('shows the aba id marker in the list title row', () => {
        const overviewPath = path.resolve(process.cwd(), 'resources/js/pages/admin/aba/components/Overview.vue')
        const overviewContent = fs.readFileSync(overviewPath, 'utf8')

        expect(overviewContent).toContain('#{{ aba.id }}')
    })

    it('disables results while an analysis start request is in-flight for the same aba', () => {
        const disabled = methods.isResultsDisabled.call({
            isRefreshing: false,
            startingAnalysisAbaId: 42,
            isAnalysisRunning: () => false,
        }, { id: '42' })

        expect(disabled).toBe(true)
    })

    it('disables results while analysis is running and re-enables after completion', () => {
        const aba = { id: 7 }

        const disabledWhileRunning = methods.isResultsDisabled.call({
            isRefreshing: false,
            startingAnalysisAbaId: null,
            isAnalysisRunning: () => true,
        }, aba)

        const enabledAfterCompletion = methods.isResultsDisabled.call({
            isRefreshing: false,
            startingAnalysisAbaId: null,
            isAnalysisRunning: () => false,
        }, aba)

        expect(disabledWhileRunning).toBe(true)
        expect(enabledAfterCompletion).toBe(false)
    })
})
