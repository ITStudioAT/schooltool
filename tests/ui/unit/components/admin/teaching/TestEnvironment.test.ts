import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import TestEnvironment from '@/pages/admin/teaching/testEnvironment/TestEnvironment.vue'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    },
}))

describe('Teaching test environment page', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('loads the current test environment status', async () => {
        const status = {
            is_configured: false,
            source_import116_count: 989,
            target_import116_count: 0,
            target_teaching_record_count: 0,
        }
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { data: status } })
        const ctx: Record<string, unknown> = {
            status: null,
            loading: false,
            error: '',
            errorMessage: (TestEnvironment as any).methods.errorMessage,
        }

        await (TestEnvironment as any).methods.loadStatus.call(ctx)

        expect(axios.get).toHaveBeenCalledWith('/api/admin/teaching/test-environment')
        expect(ctx.status).toEqual(status)
        expect(ctx.loading).toBe(false)
    })

    it('requires the explicit schoolyear confirmation before submitting', async () => {
        const ctx: Record<string, unknown> = {
            status: { is_configured: false },
            confirmationText: 'löschen',
            submitting: false,
            error: '',
        }

        await (TestEnvironment as any).methods.submit.call(ctx)

        expect(axios.post).not.toHaveBeenCalled()
        expect(axios.delete).not.toHaveBeenCalled()
    })

    it('sets up the environment when no configured marker exists', async () => {
        vi.mocked(axios.post).mockResolvedValueOnce({ data: { data: { is_configured: true } } })
        const redirectTo = vi.fn()
        const ctx: Record<string, unknown> = {
            status: { is_configured: false },
            confirmationText: '2026/27',
            submitting: false,
            error: '',
            confirmationOpen: true,
            errorMessage: (TestEnvironment as any).methods.errorMessage,
            redirectTo,
        }

        await (TestEnvironment as any).methods.submit.call(ctx)

        expect(axios.post).toHaveBeenCalledWith('/api/admin/teaching/test-environment', {
            confirmation: '2026/27',
        })
        expect(redirectTo).toHaveBeenCalledWith('/admin/teaching/testumgebung')
        expect(ctx.submitting).toBe(false)
    })

    it('deletes the configured environment', async () => {
        vi.mocked(axios.delete).mockResolvedValueOnce({ data: { data: { is_configured: false } } })
        const redirectTo = vi.fn()
        const ctx: Record<string, unknown> = {
            status: { is_configured: true },
            confirmationText: '2026/27',
            submitting: false,
            error: '',
            confirmationOpen: true,
            errorMessage: (TestEnvironment as any).methods.errorMessage,
            redirectTo,
        }

        await (TestEnvironment as any).methods.submit.call(ctx)

        expect(axios.delete).toHaveBeenCalledWith('/api/admin/teaching/test-environment', {
            data: { confirmation: '2026/27' },
        })
        expect(redirectTo).toHaveBeenCalledWith('/admin/teaching')
        expect(ctx.submitting).toBe(false)
    })
})
