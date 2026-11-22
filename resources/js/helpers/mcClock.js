export function mcRemainingSeconds(deadline, referenceTime) {
    return Math.max(0, Math.ceil((Date.parse(deadline) - referenceTime) / 1000))
}

export function formatMcClock(seconds) {
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
}
