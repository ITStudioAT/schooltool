import { describe, expect, it } from 'vitest'
import { resolveAdminEchoConfig } from '@/apps/adminEchoConfig'

describe('admin Echo configuration', () => {
    it('configures Reverb from an explicit connection', () => {
        expect(resolveAdminEchoConfig({
            VITE_BROADCAST_CONNECTION: 'reverb',
            VITE_REVERB_APP_KEY: 'public-reverb-key',
            VITE_REVERB_HOST: 'reverb.example.test',
            VITE_REVERB_PORT: '8443',
            VITE_REVERB_SCHEME: 'https',
        })).toEqual({
            broadcaster: 'reverb',
            key: 'public-reverb-key',
            wsHost: 'reverb.example.test',
            wsPort: 8443,
            wssPort: 8443,
            forceTLS: true,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
        })
    })

    it('configures Pusher from an explicit connection', () => {
        expect(resolveAdminEchoConfig({
            VITE_BROADCAST_CONNECTION: 'pusher',
            VITE_PUSHER_APP_KEY: 'public-pusher-key',
            VITE_PUSHER_APP_CLUSTER: 'eu',
        })).toEqual({
            broadcaster: 'pusher',
            key: 'public-pusher-key',
            cluster: 'eu',
            forceTLS: true,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
        })
    })

    it('supports the existing provider variable convention without an explicit connection', () => {
        expect(resolveAdminEchoConfig({
            VITE_REVERB_APP_KEY: 'public-reverb-key',
            VITE_REVERB_HOST: 'localhost',
            VITE_REVERB_PORT: '8080',
            VITE_REVERB_SCHEME: 'http',
            VITE_PUSHER_APP_KEY: 'public-pusher-key',
            VITE_PUSHER_APP_CLUSTER: 'eu',
        })).toMatchObject({
            broadcaster: 'reverb',
            wsPort: 8080,
            wssPort: 8080,
            forceTLS: false,
        })
    })

    it('supports a self-hosted Pusher-compatible endpoint', () => {
        expect(resolveAdminEchoConfig({
            VITE_BROADCAST_CONNECTION: 'pusher',
            VITE_PUSHER_APP_KEY: 'public-pusher-key',
            VITE_PUSHER_HOST: 'socket.example.test',
            VITE_PUSHER_PORT: '6001',
            VITE_PUSHER_SCHEME: 'http',
        })).toMatchObject({
            broadcaster: 'pusher',
            wsHost: 'socket.example.test',
            wsPort: 6001,
            wssPort: 6001,
            forceTLS: false,
        })
    })

    it('ignores an unresolved Pusher host placeholder and uses the configured cluster', () => {
        expect(resolveAdminEchoConfig({
            VITE_BROADCAST_CONNECTION: 'pusher',
            VITE_PUSHER_APP_KEY: 'public-pusher-key',
            VITE_PUSHER_APP_CLUSTER: 'eu',
            VITE_PUSHER_HOST: '${PUSHER_HOST}',
        })).toEqual({
            broadcaster: 'pusher',
            key: 'public-pusher-key',
            cluster: 'eu',
            forceTLS: true,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
        })
    })

    it.each([
        {
            VITE_BROADCAST_CONNECTION: 'reverb',
            VITE_REVERB_APP_KEY: 'public-reverb-key',
        },
        {
            VITE_BROADCAST_CONNECTION: 'pusher',
            VITE_PUSHER_APP_KEY: 'public-pusher-key',
        },
        {
            VITE_BROADCAST_CONNECTION: 'log',
            VITE_REVERB_APP_KEY: 'public-reverb-key',
            VITE_REVERB_HOST: 'localhost',
        },
        {
            VITE_BROADCAST_CONNECTION: 'reverb',
            VITE_REVERB_APP_KEY: 'public-reverb-key',
            VITE_REVERB_HOST: 'localhost',
            VITE_REVERB_PORT: 'invalid',
        },
        {
            VITE_BROADCAST_CONNECTION: 'pusher',
            VITE_PUSHER_APP_KEY: 'public-pusher-key',
            VITE_PUSHER_APP_CLUSTER: 'eu',
            VITE_PUSHER_SCHEME: 'ftp',
        },
        {},
    ])('falls back to the null broadcaster for incomplete or inactive configuration', (environment) => {
        expect(resolveAdminEchoConfig(environment)).toEqual({
            broadcaster: 'null',
        })
    })
})
