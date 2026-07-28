import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

describe('Register system light cards', () => {
    it('uses light surfaces on the register system overview shell and cards', () => {
        const pageSource = readFileSync('resources/js/pages/admin/registerSystem/RegisterSystem.vue', 'utf8')
        const registersSource = readFileSync('resources/js/pages/admin/registerSystem/components/RegisterSystem/Registers.vue', 'utf8')
        const activeSource = readFileSync('resources/js/pages/admin/registerSystem/components/RegisterSystem/ActiveRegisters.vue', 'utf8')

        expect(pageSource).toContain('background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);')
        expect(pageSource).toContain('background: rgba(30, 41, 59, 0.8);')

        expect(registersSource).toContain('background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(246, 250, 255, 0.92)) !important;')
        expect(registersSource).toContain('color: #10263a !important;')
        expect(registersSource).toContain('background: rgba(255, 255, 255, 0.82);')
        expect(registersSource).toContain('color: #10263a;')

        expect(activeSource).toContain('background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(246, 250, 255, 0.92)) !important;')
        expect(activeSource).toContain('color: #10263a !important;')
        expect(activeSource).toContain('color: #10263a;')
    })
})
