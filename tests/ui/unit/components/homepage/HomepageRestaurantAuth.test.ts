import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('homepage restaurant auth flow', () => {
    it('checks the email before showing the next restaurant login step', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/homepage/index/Restaurant.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("axios.post('/api/homepage/restaurant/check_email'")
        expect(source).toContain('class="restaurant-login-school"')
        expect(source).toContain('{{ schoolInfoName }}')
        expect(source).toContain('<v-form ref="loginEmailForm" @submit.prevent="submitLoginEmailCheck">')
        expect(source).toContain('Bitte geben Sie Ihre E-Mail-Adresse ein, um fortzufahren.')
        expect(source).toContain('autofocus')
        expect(source).toContain(':rules="[required(), mail(), maxLength(255)]"')
        expect(source).toContain("loginCheckResult?.status === 'USER_FOUND'")
        expect(source).toContain('Mit Code')
        expect(source).toContain('Mit Passwort')
    })

    it('offers registration when the restaurant email is unknown', () => {
        const componentPath = resolve(process.cwd(), 'resources/js/pages/homepage/index/Restaurant.vue')
        const source = readFileSync(componentPath, 'utf8')

        expect(source).toContain("loginCheckResult?.status === 'REGISTER_REQUIRED'")
        expect(source).toContain('Möchten Sie sich registrieren?')
        expect(source).toContain("loginCheckLoading || loginCheckResult?.status === 'REGISTER_REQUIRED'")
        expect(source).toContain('@click="handleLoginPrimaryAction"')
        expect(source).toContain("{{ loginCheckResult?.status === 'REGISTER_REQUIRED' ? 'Registrieren' : 'Weiter' }}")
        expect(source).toContain('switchToRegisterDialog')
        expect(source).toContain("axios.post('/api/homepage/restaurant/register'")
        expect(source).toContain(':model-value="registerEmail"')
        expect(source).toContain('readonly')
        expect(source).toContain("registerSource === 'new_user'")
        expect(source).toContain('submitRestaurantRegistration')
        expect(source).toContain('openRegisterDialog() {')
        expect(source).toContain('this.openLoginDialog()')
    })
})
