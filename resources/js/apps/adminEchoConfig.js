const authEndpoint = '/broadcasting/auth'

function value(environment, name) {
    const configuredValue = environment[name]
    const normalizedValue = typeof configuredValue === 'string'
        ? configuredValue.trim()
        : ''

    return /^\$\{[^}]+\}$/.test(normalizedValue)
        ? ''
        : normalizedValue
}

function port(environment, name, fallback) {
    const configuredValue = value(environment, name)

    if (!configuredValue) {
        return fallback
    }

    if (!/^\d+$/.test(configuredValue)) {
        return null
    }

    const configuredPort = Number.parseInt(configuredValue, 10)

    return configuredPort > 0 && configuredPort <= 65535
        ? configuredPort
        : null
}

function usesTls(environment, name) {
    const scheme = value(environment, name).toLowerCase()

    if (!scheme || scheme === 'https') {
        return true
    }

    return scheme === 'http'
        ? false
        : null
}

function nullConfig() {
    return {
        broadcaster: 'null',
    }
}

function reverbConfig(environment) {
    const key = value(environment, 'VITE_REVERB_APP_KEY')
    const wsHost = value(environment, 'VITE_REVERB_HOST')

    if (!key || !wsHost) {
        return null
    }

    const forceTLS = usesTls(environment, 'VITE_REVERB_SCHEME')

    if (forceTLS === null) {
        return null
    }

    const defaultPort = forceTLS ? 443 : 80
    const wsPort = port(environment, 'VITE_REVERB_PORT', defaultPort)

    if (wsPort === null) {
        return null
    }

    return {
        broadcaster: 'reverb',
        key,
        wsHost,
        wsPort,
        wssPort: wsPort,
        forceTLS,
        enabledTransports: ['ws', 'wss'],
        authEndpoint,
    }
}

function pusherConfig(environment) {
    const key = value(environment, 'VITE_PUSHER_APP_KEY')
    const cluster = value(environment, 'VITE_PUSHER_APP_CLUSTER')
    const wsHost = value(environment, 'VITE_PUSHER_HOST')

    if (!key || (!cluster && !wsHost)) {
        return null
    }

    const forceTLS = usesTls(environment, 'VITE_PUSHER_SCHEME')

    if (forceTLS === null) {
        return null
    }

    const config = {
        broadcaster: 'pusher',
        key,
        forceTLS,
        enabledTransports: ['ws', 'wss'],
        authEndpoint,
    }

    if (cluster) {
        config.cluster = cluster
    }

    if (wsHost) {
        const defaultPort = forceTLS ? 443 : 80
        const configuredPort = port(environment, 'VITE_PUSHER_PORT', defaultPort)

        if (configuredPort === null) {
            return null
        }

        config.wsHost = wsHost
        config.wsPort = configuredPort
        config.wssPort = configuredPort
    }

    return config
}

export function resolveAdminEchoConfig(environment) {
    const connection = value(environment, 'VITE_BROADCAST_CONNECTION').toLowerCase()

    if (connection) {
        if (connection === 'reverb') {
            return reverbConfig(environment) ?? nullConfig()
        }

        if (connection === 'pusher') {
            return pusherConfig(environment) ?? nullConfig()
        }

        return nullConfig()
    }

    return reverbConfig(environment)
        ?? pusherConfig(environment)
        ?? nullConfig()
}
