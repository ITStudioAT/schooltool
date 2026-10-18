export function rateLimitRetrySeconds(headers) {
    const value = headers?.get?.('retry-after') ?? headers?.['retry-after'] ?? headers?.['Retry-After']
    if (value !== undefined && value !== null && String(value).trim() !== '') {
        const seconds = Number(value)
        if (Number.isFinite(seconds) && seconds >= 0) return Math.max(1, Math.ceil(seconds))
        const date = Date.parse(value)
        if (Number.isFinite(date)) return Math.max(1, Math.ceil((date - Date.now()) / 1000))
    }
    return 60
}
