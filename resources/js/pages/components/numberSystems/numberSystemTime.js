export function formatGameTime(seconds) {
    const value = Math.max(0, Math.floor(seconds ?? 0))
    const hours = Math.floor(value / 3600)
    const minutes = Math.floor(value / 60) % 60
    return `${hours ? `${hours}:` : ''}${String(hours ? minutes : Math.floor(value / 60)).padStart(2, '0')}:${String(value % 60).padStart(2, '0')}`
}

export function gameClockSeconds(clock, anchor, current) {
    return Math.max(0, (clock?.elapsed_seconds ?? 0) + (clock?.ticking ? Math.floor(Math.max(0, current - anchor) / 1000) : 0))
}
