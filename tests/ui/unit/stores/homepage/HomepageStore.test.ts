import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { homepageStartupRequestTimeout, useHomepageStore } from '@/stores/homepage/HomepageStore'
import { useNotificationStore } from '@/stores/spa/NotificationStore'

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: vi.fn(),
}))

describe('HomepageStore startup', () => {
    const get = vi.fn()
    const notify = vi.fn()

    beforeEach(() => {
        setActivePinia(createPinia())
        get.mockReset()
        notify.mockReset()
        vi.stubGlobal('axios', { get })
        vi.mocked(useNotificationStore).mockReturnValue({ notify } as never)
    })

    afterEach(() => vi.unstubAllGlobals())

    it('reports an interrupted configuration request without leaving the loading state active', async () => {
        get.mockRejectedValue(new Error('Network Error'))
        const store = useHomepageStore()

        await expect(store.loadConfig(null, null, { timeout: homepageStartupRequestTimeout })).resolves.toBe(false)

        expect(store.is_loading).toBe(0)
        expect(notify).toHaveBeenCalledWith(expect.objectContaining({
            status: null,
            message: 'Die Homepage-Konfiguration konnte nicht geladen werden.',
            type: 'error',
        }))
    })
})
