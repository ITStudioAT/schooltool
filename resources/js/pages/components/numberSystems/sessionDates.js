const formatter = new Intl.DateTimeFormat('de-AT', {
    timeZone: 'Europe/Vienna', day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
})

function dateParts(timestamp) {
    if (!timestamp || !Number.isFinite(Date.parse(timestamp))) { return null }
    const parts = Object.fromEntries(formatter.formatToParts(new Date(timestamp)).map(part => [part.type, part.value]))
    return { date: `${parts.day}.${parts.month}.`, year: parts.year, time: `${parts.hour}:${parts.minute}` }
}

export function numberSystemSessionDates(session) {
    const started = dateParts(session.started_at)
    const ended = dateParts(session.ended_at)
    const created = dateParts(session.created_at)
    if (started && ended) {
        if (started.date === ended.date && started.year === ended.year) { return `${started.date} ${started.time}–${ended.time}` }
        const differentYears = started.year !== ended.year
        return `${started.date}${differentYears ? started.year : ''} ${started.time}–${ended.date}${differentYears ? ended.year : ''} ${ended.time}`
    }
    if (started) { return `${started.date} ${started.time}` }
    if (ended) { return `Ende: ${ended.date} ${ended.time}` }
    return created ? `Vorbereitet: ${created.date} ${created.time}` : 'Noch nicht gespielt'
}
