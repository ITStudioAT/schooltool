import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import IndexPage from '@/pages/admin/index/Index.vue'

const notifyMock = vi.fn()

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: () => ({
        notify: notifyMock,
    }),
}))

function createDeferred<T>() {
    let resolve!: (value: T | PromiseLike<T>) => void
    let reject!: (reason?: unknown) => void
    const promise = new Promise<T>((res, rej) => {
        resolve = res
        reject = rej
    })

    return { promise, resolve, reject }
}

describe('Index restartQueues', () => {
    beforeEach(() => {
        notifyMock.mockReset()
        vi.useFakeTimers()
    })

    afterEach(() => {
        vi.useRealTimers()
    })

    it('counts seconds while restart and tests are running', async () => {
        const restartRequest = createDeferred<void>()
        const testsRun = createDeferred<void>()

        globalThis.axios = {
            post: vi.fn(() => restartRequest.promise),
        } as never

        const restartQueues = (IndexPage as any).methods.restartQueues
        const context: Record<string, any> = {
            restart_queues_loading: false,
            restart_countdown: 0,
            runTests: vi.fn(() => testsRun.promise),
        }

        const promise = restartQueues.call(context)

        expect(context.restart_queues_loading).toBe(true)
        expect(context.restart_countdown).toBe(0)

        await vi.advanceTimersByTimeAsync(2500)
        expect(context.restart_countdown).toBe(2)

        restartRequest.resolve()
        await Promise.resolve()

        expect(context.runTests).toHaveBeenCalledTimes(1)

        await vi.advanceTimersByTimeAsync(1500)
        expect(context.restart_countdown).toBe(4)

        testsRun.resolve()
        await promise

        expect(notifyMock).toHaveBeenCalledWith(
            expect.objectContaining({
                type: 'success',
            })
        )
        expect(context.restart_queues_loading).toBe(false)
        expect(context.restart_countdown).toBe(0)
    })

    it('does nothing while a restart is already running', async () => {
        const postMock = vi.fn()
        globalThis.axios = {
            post: postMock,
        } as never

        const restartQueues = (IndexPage as any).methods.restartQueues
        const context: Record<string, any> = {
            restart_queues_loading: true,
            restart_countdown: 3,
            runTests: vi.fn(),
        }

        await restartQueues.call(context)

        expect(postMock).not.toHaveBeenCalled()
        expect(context.runTests).not.toHaveBeenCalled()
    })

    it('does nothing while queue tests are running', async () => {
        const postMock = vi.fn()
        globalThis.axios = {
            post: postMock,
        } as never

        const restartQueues = (IndexPage as any).methods.restartQueues
        const context: Record<string, any> = {
            restart_queues_loading: false,
            restart_countdown: 0,
            queue_test_status: 'running',
            cron_test_status: 'waiting',
            runTests: vi.fn(),
        }

        await restartQueues.call(context)

        expect(postMock).not.toHaveBeenCalled()
        expect(context.runTests).not.toHaveBeenCalled()
        expect(context.restart_queues_loading).toBe(false)
    })
})
