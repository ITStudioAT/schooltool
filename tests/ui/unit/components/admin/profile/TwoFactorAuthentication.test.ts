import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import TwoFactorAuthentication from '@/pages/admin/profile/components/TwoFactorAuthentication.vue'

const methods = (TwoFactorAuthentication as any).methods

describe('Two-factor profile component', () => {
    it('keeps sensitive setup data in component memory and provides all required actions', () => {
        const source = readFileSync(
            join(process.cwd(), 'resources/js/pages/admin/profile/components/TwoFactorAuthentication.vue'),
            'utf8',
        )

        expect(source).toContain('Two-factor authentication')
        expect(source).toContain('qrCodeSvg')
        expect(source).toContain('manualKey')
        expect(source).toContain('Wiederherstellungscodes anzeigen')
        expect(source).toContain('Codes neu erzeugen')
        expect(source).toContain('2FA deaktivieren')
        expect(source).toContain('Einrichtung abbrechen')
        expect(source).not.toContain('localStorage')
        expect(source).not.toContain('sessionStorage')
    })

    it('normalizes a spaced six-digit confirmation code and exposes recovery codes after success', async () => {
        const emit = vi.fn()
        const confirmTwoFactorAuthentication = vi.fn().mockResolvedValue({
            enabled: true,
            pending: false,
            confirmed_at: '2026-07-21T10:00:00+00:00',
            recovery_codes: ['one', 'two'],
        })
        const context: any = {
            confirmationCode: ' 123 456 ',
            submitting: false,
            fieldError: '',
            errorMessage: '',
            successMessage: '',
            setup: { qrCodeSvg: '<svg />', manualKey: 'secret' },
            recoveryCodes: [],
            status: {},
            userStore: { confirmTwoFactorAuthentication },
            $emit: emit,
            applyStatus: methods.applyStatus,
            validationMessage: methods.validationMessage,
            requestPassword: vi.fn(),
        }

        await methods.confirmSetup.call(context)

        expect(confirmTwoFactorAuthentication).toHaveBeenCalledWith('123456')
        expect(context.recoveryCodes).toEqual(['one', 'two'])
        expect(context.setup).toEqual({ qrCodeSvg: '', manualKey: '' })
        expect(emit).toHaveBeenCalledWith('updated', {
            enabled: true,
            pending: false,
            confirmed_at: '2026-07-21T10:00:00+00:00',
        })
    })

    it('clears all sensitive values when the component is destroyed', () => {
        const context: any = {
            password: 'password',
            confirmationCode: '123456',
            setup: { qrCodeSvg: '<svg />', manualKey: 'secret' },
            recoveryCodes: ['one'],
        }

        methods.clearSensitiveState.call(context)

        expect(context).toMatchObject({
            password: '',
            confirmationCode: '',
            setup: { qrCodeSvg: '', manualKey: '' },
            recoveryCodes: [],
        })
    })
})
