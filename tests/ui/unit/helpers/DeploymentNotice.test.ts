import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { startDeploymentNotice } from '@/helpers/deploymentNotice'

const storageKey = 'schooltool-deployment-id'

function jsonResponse(data, ok = true) {
    return { ok, json: vi.fn().mockResolvedValue(data) }
}

describe('deployment notices', () => {
    let monitor
    let fetchStatus
    let reload

    beforeEach(() => {
        vi.useFakeTimers()
        window.sessionStorage.clear()
        document.body.innerHTML = '<main><input value="Unsaved lesson plan"></main>'
        fetchStatus = vi.fn().mockResolvedValue(jsonResponse({ state: 'idle', id: null }))
        reload = vi.fn()
    })

    afterEach(() => {
        monitor?.stop()
        vi.restoreAllMocks()
        vi.useRealTimers()
    })

    async function start(options = {}) {
        monitor = startDeploymentNotice({ fetchStatus, reload, ...options })
        await monitor.checkNow()
    }

    function notice() {
        return document.querySelector('[role="status"]')
    }

    async function setStatus(state, id = 'release-1') {
        fetchStatus.mockResolvedValue(jsonResponse({ state, id }))
        await monitor.checkNow()
    }

    it.each(['idle', 'completed'])('keeps a fresh page quiet when status is %s', async (state) => {
        fetchStatus.mockResolvedValue(jsonResponse({ state, id: 'old-release' }))
        await start()

        expect(notice()).toBeNull()
        expect(fetchStatus).toHaveBeenCalledTimes(1)
        expect(reload).not.toHaveBeenCalled()
    })

    it('announces deployment, preserves input and refreshes only on an explicit click', async () => {
        const input = document.querySelector('input')
        await start()
        await setStatus('scheduled')
        expect(notice()?.textContent).toContain('Bitte speichern Sie jetzt')
        expect(window.sessionStorage.getItem(storageKey)).toBe('release-1')

        await setStatus('maintenance')
        expect(notice()?.textContent).toContain('Speichern ist vorübergehend nicht möglich')

        fetchStatus.mockResolvedValueOnce(jsonResponse({ state: 'completed', id: 'release-1' }))
            .mockResolvedValueOnce(jsonResponse({ status: 'up' }))
        await monitor.checkNow()

        expect(notice()?.textContent).toContain('SchoolTool ist wieder verfügbar')
        expect(document.querySelector('input')).toBe(input)
        expect(input?.value).toBe('Unsaved lesson plan')
        expect(window.sessionStorage.getItem(storageKey)).toBeNull()
        expect(reload).not.toHaveBeenCalled()
        expect(notice()?.querySelector('a')?.getAttribute('href')).toBe('/documentation/releases/')
        expect(notice()?.querySelector('a')?.getAttribute('target')).toBe('_blank')
        notice()?.querySelector('.schooltool-deployment-notice__actions button')?.click()
        expect(reload).toHaveBeenCalledOnce()
    })

    it('confirms health before recovering from idle after an observed deployment', async () => {
        await start()
        await setStatus('maintenance')
        fetchStatus.mockResolvedValueOnce(jsonResponse({ state: 'idle', id: null }))
            .mockResolvedValueOnce(jsonResponse({ status: 'down' }))
        await monitor.checkNow()
        expect(notice()?.querySelector('strong')?.textContent).not.toContain('wieder verfügbar')

        fetchStatus.mockResolvedValueOnce(jsonResponse({ state: 'idle', id: null }))
            .mockResolvedValueOnce(jsonResponse({ status: 'up' }))
        await monitor.checkNow()
        expect(notice()?.textContent).toContain('wieder verfügbar')
        expect(fetchStatus).toHaveBeenLastCalledWith('/up', expect.objectContaining({
            cache: 'no-store', headers: { Accept: 'application/json' },
        }))
    })

    it.each(['maintenance', 'failed'])('keeps dismissed warnings quiet and shows the next %s notice', async (nextState) => {
        await start()
        await setStatus('scheduled')
        notice()?.querySelector('[aria-label="Hinweis schließen"]')?.click()
        expect(notice()).not.toBeVisible()
        await setStatus('scheduled')
        expect(notice()).not.toBeVisible()
        await setStatus(nextState)
        expect(notice()).toBeVisible()
        expect(notice()?.querySelector('[aria-label="Hinweis schließen"]')).toBeNull()
        expect(reload).not.toHaveBeenCalled()
    })

    it('shows an unavailable notice after a dismissed warning and an outage', async () => {
        await start()
        await setStatus('scheduled')
        notice()?.querySelector('[aria-label="Hinweis schließen"]')?.click()
        fetchStatus.mockRejectedValue(new Error('Network offline'))
        await monitor.checkNow()
        expect(notice()).toBeVisible()
        expect(notice()?.textContent).toContain('Verbindung unterbrochen')
        expect(reload).not.toHaveBeenCalled()
    })

    it('allows dismissing recovery without reloading or redisplaying it on the next poll', async () => {
        window.sessionStorage.setItem(storageKey, 'release-1')
        fetchStatus.mockResolvedValueOnce(jsonResponse({ state: 'completed', id: 'release-1' }))
            .mockResolvedValueOnce(jsonResponse({ status: 'up' }))
        await start()
        notice()?.querySelector('[aria-label="Hinweis schließen"]')?.click()
        expect(notice()).not.toBeVisible()
        await setStatus('completed')
        expect(notice()).not.toBeVisible()
        expect(reload).not.toHaveBeenCalled()
    })

    it('shows recovery after returning from the standalone maintenance page', async () => {
        window.sessionStorage.setItem(storageKey, 'release-1')
        fetchStatus.mockResolvedValueOnce(jsonResponse({ state: 'completed', id: 'release-1' }))
            .mockResolvedValueOnce(jsonResponse({ status: 'up' }))
        await start()

        expect(notice()?.textContent).toContain('wieder verfügbar')
        expect(reload).not.toHaveBeenCalled()
    })

    it.each([
        null,
        { state: 'unknown', id: 'release-1' },
        { state: 'maintenance' },
        { state: 'maintenance', id: {} },
    ])('ignores malformed status without inventing a maintenance event: %j', async (data) => {
        fetchStatus.mockResolvedValue(jsonResponse(data))
        await start()
        expect(notice()).toBeNull()
    })

    it('keeps ordinary outages quiet and retains a notice during an announced outage', async () => {
        fetchStatus.mockRejectedValue(new Error('Network offline'))
        await start()
        expect(notice()).toBeNull()
        await setStatus('scheduled')
        fetchStatus.mockRejectedValue(new Error('Network offline'))
        await monitor.checkNow()
        expect(notice()?.textContent).toContain('Verbindung unterbrochen')
        expect(notice()?.querySelector('strong')?.textContent).not.toContain('wieder verfügbar')
        expect(reload).not.toHaveBeenCalled()
    })

    it('keeps a failed deployment visible until the server releases it', async () => {
        await start()
        await setStatus('failed')
        expect(notice()?.textContent).toContain('Aktualisierung verzögert')
        expect(fetchStatus).not.toHaveBeenCalledWith('/up', expect.anything())
    })

    it.each([500, 502, 503, 504])('checks %s failures immediately without retrying writes or changing Axios results', async (status) => {
        const axiosClient = { interceptors: { response: { use: vi.fn().mockReturnValue(7), eject: vi.fn() } } }
        await start({ axiosClient })
        const [onSuccess, onFailure] = axiosClient.interceptors.response.use.mock.calls[0]
        const response = { data: 'saved' }
        expect(onSuccess(response)).toBe(response)
        const failure = { response: { status }, config: { method: 'post' } }
        await expect(onFailure(failure)).rejects.toBe(failure)
        await vi.advanceTimersByTimeAsync(0)
        expect(fetchStatus).toHaveBeenCalledTimes(2)
        expect(notice()).toBeNull()
        monitor.stop()
        expect(axiosClient.interceptors.response.eject).toHaveBeenCalledWith(7)
    })

    it('polls every ten seconds without overlapping requests and cleans up', async () => {
        await start()
        let finish
        fetchStatus.mockImplementationOnce(() => new Promise((resolve) => { finish = resolve }))
        await vi.advanceTimersByTimeAsync(10000)
        expect(fetchStatus).toHaveBeenCalledTimes(2)
        void monitor.checkNow()
        expect(fetchStatus).toHaveBeenCalledTimes(2)
        finish(jsonResponse({ state: 'scheduled', id: 'release-1' }))
        await monitor.checkNow()
        expect(notice()).not.toBeNull()
        monitor.stop()
        await vi.advanceTimersByTimeAsync(30000)
        expect(fetchStatus).toHaveBeenCalledTimes(2)
        expect(notice()).toBeNull()
    })

    it('aborts stalled requests and resumes polling', async () => {
        fetchStatus.mockImplementationOnce((url, options) => new Promise((resolve, reject) => {
            options.signal.addEventListener('abort', () => reject(new Error('Aborted')))
        }))
        monitor = startDeploymentNotice({ fetchStatus, reload })
        await vi.advanceTimersByTimeAsync(5000)
        expect(fetchStatus.mock.calls[0][1].signal.aborted).toBe(true)
        await vi.advanceTimersByTimeAsync(10000)
        expect(fetchStatus).toHaveBeenCalledTimes(2)
        expect(notice()).toBeNull()
    })

    it('still works when sessionStorage is inaccessible', async () => {
        vi.spyOn(window, 'sessionStorage', 'get').mockImplementation(() => { throw new Error('Storage disabled') })
        fetchStatus.mockResolvedValue(jsonResponse({ state: 'scheduled', id: 'release-1' }))
        await start()
        expect(notice()?.textContent).toContain('Aktualisierung angekündigt')
    })
})
