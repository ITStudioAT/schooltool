import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('Admin login remember me option', () => {
    it('renders remember me checkbox in password step', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/auth/Login.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('data-testid="admin-login-remember"')
        expect(source).toContain('label="Angemeldet bleiben"')
    })

    it('initializes remember me as enabled on login restart', () => {
        const componentPath = resolve(
            process.cwd(),
            'resources/js/pages/admin/auth/Login.vue',
        )
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain('this.data.remember = true')
    })
})
