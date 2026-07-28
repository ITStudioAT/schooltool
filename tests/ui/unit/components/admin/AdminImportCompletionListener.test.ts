import { readFileSync } from 'node:fs'
import { shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AdminImportCompletionListener from '@/pages/admin/components/AdminImportCompletionListener.vue'

const { notifyMock, useEchoMock } = vi.hoisted(() => ({
    notifyMock: vi.fn(),
    useEchoMock: vi.fn(),
}))

vi.mock('@laravel/echo-vue', () => ({
    useEcho: useEchoMock,
}))

vi.mock('@/stores/spa/NotificationStore', () => ({
    useNotificationStore: () => ({
        notify: notifyMock,
    }),
}))

describe('Admin import completion listener', () => {
    beforeEach(() => {
        notifyMock.mockReset()
        useEchoMock.mockReset()
    })

    it('subscribes to both existing events on the private user channel', () => {
        shallowMount(AdminImportCompletionListener, {
            props: {
                userId: 42,
            },
        })

        expect(useEchoMock).toHaveBeenCalledTimes(2)
        expect(useEchoMock).toHaveBeenNthCalledWith(
            1,
            'user.42',
            'TeachersListImportFinishedEvent',
            expect.any(Function),
        )
        expect(useEchoMock).toHaveBeenNthCalledWith(
            2,
            'user.42',
            'Import116FinishedEvent',
            expect.any(Function),
        )
    })

    it('notifies for both events and forwards Import116 completion to polling consumers', () => {
        const importFinishedListener = vi.fn()
        window.addEventListener('import116-finished', importFinishedListener)

        shallowMount(AdminImportCompletionListener, {
            props: {
                userId: 7,
            },
        })

        const teachersListCallback = useEchoMock.mock.calls[0][2]
        const import116Callback = useEchoMock.mock.calls[1][2]
        const teachersListPayload = {
            status: 200,
            message: 'Lehrerliste importiert.',
            data: { created: 2 },
        }
        const import116Payload = {
            status: 500,
            message: 'Import 116 fehlgeschlagen.',
            data: { run_id: 9 },
        }

        teachersListCallback(teachersListPayload)
        import116Callback(import116Payload)

        expect(notifyMock).toHaveBeenNthCalledWith(1, {
            message: teachersListPayload.message,
            type: 'success',
            persistent: true,
        })
        expect(notifyMock).toHaveBeenNthCalledWith(2, {
            message: import116Payload.message,
            type: 'error',
            persistent: true,
        })
        expect(importFinishedListener).toHaveBeenCalledTimes(1)
        expect((importFinishedListener.mock.calls[0][0] as CustomEvent).detail).toEqual(import116Payload)

        window.removeEventListener('import116-finished', importFinishedListener)
    })

    it('is mounted once per authenticated user identity', () => {
        const appSource = readFileSync('resources/js/pages/admin/App.vue', 'utf8')

        expect(appSource).toContain('v-if="config.is_auth && config.user?.id"')
        expect(appSource).toContain(':key="config.user.id"')
        expect(appSource).toContain(':user-id="config.user.id"')
    })
})
