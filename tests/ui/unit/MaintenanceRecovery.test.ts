import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const html = readFileSync(resolve('public/maintenance.html'), 'utf8')
const script = html.match(/<script>([\s\S]*?)<\/script>/)![1]

function response(data: unknown, ok = true) {
    return { ok, json: async () => data }
}

function start(fetch: ReturnType<typeof vi.fn>, path = '/student/course/17?panel=dates') {
    const replace = vi.fn()
    const setItem = vi.fn()
    document.body.innerHTML = html.match(/<body>([\s\S]*?)<script>/)![1]
    new Function('fetch', 'window', 'sessionStorage', script)(fetch, {
        location: { pathname: path.split('?')[0], href: `https://schooltool.test${path}`, replace },
    }, { setItem })
    return { replace, setItem }
}

beforeEach(() => vi.useFakeTimers())
afterEach(() => {
    vi.clearAllTimers()
    vi.useRealTimers()
    document.body.innerHTML = ''
})

describe('maintenance page recovery', () => {
    it('waits through maintenance and returns to the original URL only after Laravel is healthy', async () => {
        const fetch = vi.fn()
            .mockResolvedValueOnce(response({ state: 'maintenance', id: 'deployment-1' }))
            .mockResolvedValueOnce(response({ state: 'completed', id: 'deployment-1' }))
            .mockResolvedValueOnce(response({ status: 'up' }))
        const { replace, setItem } = start(fetch)
        await vi.advanceTimersByTimeAsync(0)
        expect(replace).not.toHaveBeenCalled()
        expect(fetch).toHaveBeenCalledTimes(1)
        expect(setItem).toHaveBeenCalledWith('schooltool-deployment-id', 'deployment-1')

        await vi.advanceTimersByTimeAsync(15000)
        expect(fetch.mock.calls.map(call => call[0])).toEqual(['/deployment-status.php', '/deployment-status.php', '/up'])
        expect(replace).toHaveBeenCalledWith('https://schooltool.test/student/course/17?panel=dates')
        expect(fetch.mock.calls[2][1]).toMatchObject({ cache: 'no-store', headers: { Accept: 'application/json' } })
    })

    it('opens the homepage after visiting the maintenance file directly', async () => {
        const fetch = vi.fn()
            .mockResolvedValueOnce(response({ state: 'idle', id: null }))
            .mockResolvedValueOnce(response({ status: 'up' }))
        const { replace } = start(fetch, '/maintenance.html')
        await vi.advanceTimersByTimeAsync(0)
        expect(replace).toHaveBeenCalledWith('/')
    })

    it.each([
        ['failed', 'Die Aktualisierung benötigt noch etwas Zeit.'],
        ['maintenance', 'Die Aktualisierung läuft noch.'],
        ['scheduled', 'Die Aktualisierung läuft noch.'],
    ])('keeps waiting while status is %s', async (state, message) => {
        const fetch = vi.fn().mockResolvedValue(response({ state, id: 'deployment-1' }))
        const { replace } = start(fetch)
        await vi.advanceTimersByTimeAsync(0)
        expect(replace).not.toHaveBeenCalled()
        expect(document.getElementById('maintenance-status')?.textContent).toContain(message)
        expect(fetch).toHaveBeenCalledTimes(1)
    })

    it('does not navigate when the status is ready but Laravel still fails', async () => {
        const fetch = vi.fn()
            .mockResolvedValueOnce(response({ state: 'completed', id: 'deployment-1' }))
            .mockResolvedValueOnce(response({ status: 'down' }, false))
        const { replace } = start(fetch)
        await vi.advanceTimersByTimeAsync(0)
        expect(replace).not.toHaveBeenCalled()
        expect(document.getElementById('maintenance-status')?.textContent).toContain('noch nicht erreichbar')
    })

    it('allows a manual retry after a temporary network error', async () => {
        const fetch = vi.fn()
            .mockRejectedValueOnce(new Error('Offline'))
            .mockResolvedValueOnce(response({ state: 'idle', id: null }))
            .mockResolvedValueOnce(response({ status: 'up' }))
        const { replace } = start(fetch)
        await vi.advanceTimersByTimeAsync(0)
        expect(replace).not.toHaveBeenCalled()
        document.getElementById('maintenance-retry')!.click()
        await vi.advanceTimersByTimeAsync(0)
        expect(replace).toHaveBeenCalledOnce()
    })
})
