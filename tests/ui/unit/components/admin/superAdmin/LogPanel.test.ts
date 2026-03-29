import { describe, expect, it, vi } from 'vitest'
import Log from '@/pages/admin/superAdmin/components/Log.vue'

describe('Super admin log panel wrapper', () => {
    it('uses an embedded wrapper without dialog props when embedded mode is enabled', () => {
        const ctx = {
            embedded: true,
            modelValue: false,
            $emit: vi.fn(),
        }

        expect((Log as any).computed.wrapperComponent.call(ctx)).toBe('div')
        expect((Log as any).computed.wrapperProps.call(ctx)).toEqual({})
        expect((Log as any).computed.wrapperListeners.call(ctx)).toEqual({})
    })

    it('uses the dialog wrapper and forwards model updates in modal mode', () => {
        const emit = vi.fn()
        const ctx = {
            embedded: false,
            modelValue: true,
            $emit: emit,
        }

        expect((Log as any).computed.wrapperComponent.call(ctx)).toBe('v-dialog')
        expect((Log as any).computed.wrapperProps.call(ctx)).toEqual({
            modelValue: true,
            persistent: true,
            maxWidth: 1300,
        })

        const listeners = (Log as any).computed.wrapperListeners.call(ctx)
        listeners['update:modelValue'](false)

        expect(emit).toHaveBeenCalledWith('update:modelValue', false)
    })
})
