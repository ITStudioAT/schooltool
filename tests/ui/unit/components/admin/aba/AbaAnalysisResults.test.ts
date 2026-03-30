import { existsSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

describe('AbaAnalysisResults', () => {
    it('is removed from the aba frontend bundle', () => {
        expect(existsSync('resources/js/pages/admin/aba/AbaAnalysisResults.vue')).toBe(false)
    })
})
