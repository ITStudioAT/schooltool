import { parseLocalDate } from '@/helpers/date'

export const personalAppointmentKinds = [
    { value: 'standby', title: 'Allgemeine Bereitschaft' },
    { value: 'lunch_supervision', title: 'Betreute Mittagspause' },
    { value: 'break_supervision', title: 'Pausenaufsicht' },
    { value: 'special_assignment', title: 'Sondereinsatz' },
    { value: 'consultation', title: 'Sprechstunde' },
    { value: 'supplier_standby', title: 'Supplierbereitschaft' },
    { value: 'day_care_standby', title: 'Tagesbetreuung' },
]

export function personalAppointmentDateShortcuts(schoolyear, startDate) {
    const dateOnly = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
    const configuredDate = (value) => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return null
        const date = parseLocalDate(value)
        return Number.isFinite(date.getTime()) && dateOnly(date) === value ? value : null
    }
    const from = configuredDate(schoolyear?.from)
    const until = configuredDate(schoolyear?.until)
    if (from && until && from > until) return { from: null, semesterStart: null, until: null, semesterEnd: null }
    const semesterStart = configuredDate(schoolyear?.sem_2_start)
    const validSemesterStart = semesterStart && (!from || semesterStart >= from) && (!until || semesterStart <= until) ? semesterStart : null
    const start = configuredDate(startDate)
    let semesterEnd = null
    if (from && until && validSemesterStart && start && start >= from && start <= until) {
        if (start >= validSemesterStart) semesterEnd = until
        else {
            const lastDay = parseLocalDate(validSemesterStart)
            lastDay.setDate(lastDay.getDate() - 1)
            semesterEnd = dateOnly(lastDay)
        }
    }
    return { from, semesterStart: validSemesterStart, until, semesterEnd }
}

export function personalAppointmentLabel(appointment) {
    const key = `${appointment.occurrenceDate}:${appointment.occurrenceHour ?? 'all'}`
    if (Object.hasOwn(appointment.title_exceptions || {}, key)) {
        return appointment.title_exceptions[key] || personalAppointmentKinds.find((kind) => kind.value === appointment.kind)?.title || 'Termin'
    }
    return appointment.title || personalAppointmentKinds.find((kind) => kind.value === appointment.kind)?.title || 'Termin'
}

export function personalAppointmentSegments(appointment) {
    return appointment.time_segments?.length ? appointment.time_segments : [{ starts_at: appointment.starts_at, ends_at: appointment.ends_at }]
}

export function personalAppointmentTimeLabel(appointment) {
    return personalAppointmentSegments(appointment).map((segment) => `${segment.hour ? `${segment.hour}. Std · ` : ''}${segment.starts_at}–${segment.ends_at}`).join(' · ')
}

export function personalAppointmentDuration(segments) {
    const minutes = (value) => {
        const match = /^(\d{2}):(\d{2})/.exec(value || '')
        return match && Number(match[1]) < 24 && Number(match[2]) < 60 ? Number(match[1]) * 60 + Number(match[2]) : null
    }
    return segments.reduce((total, segment) => {
        const start = minutes(segment.starts_at)
        const end = minutes(segment.ends_at)
        return total + (start !== null && end !== null && end > start ? end - start : 0)
    }, 0)
}

export function personalAppointmentHeight(duration, schoolHourMeasurements) {
    const scales = schoolHourMeasurements.filter((entry) => entry.minutes > 0 && entry.height > 0)
        .map((entry) => entry.height / entry.minutes).sort((left, right) => left - right)
    if (!scales.length || !Number.isFinite(duration) || duration <= 0) return null
    const middle = Math.floor(scales.length / 2)
    const scale = scales.length % 2 ? scales[middle] : (scales[middle - 1] + scales[middle]) / 2
    return duration * scale
}

export function personalAppointmentTimeline(placements, startsAt) {
    let previousEnd = startsAt
    return placements.flatMap((placement) => placement.segments.map((segment) => ({ ...placement, segments: [segment] })))
        .sort((left, right) => left.segments[0].starts_at.localeCompare(right.segments[0].starts_at))
        .map((placement) => {
            const segment = placement.segments[0]
            const gapMinutes = personalAppointmentDuration([{ starts_at: previousEnd, ends_at: segment.starts_at }])
            previousEnd = !previousEnd || segment.ends_at > previousEnd ? segment.ends_at : previousEnd
            return { ...placement, gapMinutes }
        })
}

export function personalAppointmentGridPlacement(appointment, schoolHours) {
    const configuredHours = schoolHours.map((entry) => ({ hour: Number(entry.hour), starts_at: entry.from?.slice(0, 5), ends_at: entry.until?.slice(0, 5) }))
        .filter((entry) => Number.isInteger(entry.hour) && entry.hour > 0 && entry.starts_at && entry.ends_at > entry.starts_at)
    const blocks = []
    const outside = []
    const gaps = []
    const addBlock = (hours, segments) => {
        const sorted = [...new Set(hours)].sort((left, right) => left - right)
        for (const hour of sorted) {
            const previous = blocks.at(-1)
            if (previous && previous.hours.at(-1) + 1 === hour && previous.source === segments) previous.hours.push(hour)
            else blocks.push({ hours: [hour], time_segments: segments, source: segments })
        }
    }
    const segments = personalAppointmentSegments(appointment)
    const selected = segments.filter((segment) => segment.hour)
    if (selected.length) {
        const hours = selected.map((segment) => Number(segment.hour))
        addBlock(hours, selected)
        for (const block of blocks) block.time_segments = selected.filter((segment) => block.hours.includes(Number(segment.hour)))
    } else {
        for (const segment of segments) {
            const matching = configuredHours.filter((entry) => segment.starts_at < entry.ends_at && segment.ends_at > entry.starts_at)
            if (!matching.length) {
                const ordered = [...configuredHours].sort((left, right) => left.starts_at.localeCompare(right.starts_at))
                const previous = ordered.filter((entry) => entry.ends_at <= segment.starts_at).at(-1)
                const next = ordered.find((entry) => entry.starts_at >= segment.ends_at)
                if (previous && (!next || next.starts_at >= segment.ends_at)) gaps.push({ afterHour: previous.hour, segment })
                else outside.push(segment)
                continue
            }
            addBlock(matching.map((entry) => entry.hour), [segment])
            const first = matching.map((entry) => entry.starts_at).sort()[0]
            const last = matching.map((entry) => entry.ends_at).sort().at(-1)
            if (segment.starts_at < first) {
                const preceding = configuredHours.filter((entry) => entry.ends_at <= segment.starts_at).sort((left, right) => left.ends_at.localeCompare(right.ends_at)).at(-1)
                const partial = { ...segment, ends_at: first }
                if (preceding) gaps.push({ afterHour: preceding.hour, segment: partial })
                else outside.push(partial)
            }
            if (segment.ends_at > last) gaps.push({ afterHour: matching.find((entry) => entry.ends_at === last).hour, segment: { ...segment, starts_at: last } })
        }
    }
    return { appointment, blocks, outside, gaps }
}

export function nextPersonalAppointmentDate(appointments, from) {
    const first = Date.parse(`${from}T00:00:00Z`)
    const week = 7 * 86400000
    return appointments.map((appointment) => {
        const start = Date.parse(`${appointment.date}T00:00:00Z`)
        const end = Date.parse(`${appointment.repeat_until || appointment.date}T00:00:00Z`)
        const next = appointment.repeat_until ? start + Math.max(0, Math.ceil((first - start) / week)) * week : start
        return next >= first && next <= end ? new Date(next).toISOString().slice(0, 10) : null
    }).filter(Boolean).sort()[0] || null
}

export function personalAppointmentOccurrences(appointments, from, until) {
    const day = (value) => Date.parse(`${value}T00:00:00Z`)
    const first = day(from)
    const last = day(until)
    if (!Number.isFinite(first) || !Number.isFinite(last) || first > last) return []
    const week = 7 * 86400000
    return appointments.flatMap((appointment) => {
        const start = day(appointment.date)
        const end = day(appointment.repeat_until || appointment.date)
        if (!Number.isFinite(start) || !Number.isFinite(end) || end < first || start > last) return []
        if (!appointment.repeat_until) return start >= first ? [{ ...appointment, occurrenceDate: appointment.date }] : []
        const occurrences = []
        for (let current = start + Math.max(0, Math.ceil((first - start) / week)) * week; current <= Math.min(end, last); current += week) {
            occurrences.push({ ...appointment, occurrenceDate: new Date(current).toISOString().slice(0, 10) })
        }
        return occurrences
    }).flatMap((appointment) => {
        const segments = personalAppointmentSegments(appointment)
        const hasHourException = segments.some((segment) => Object.hasOwn(appointment.title_exceptions || {}, `${appointment.occurrenceDate}:${segment.hour}`))
        if (!hasHourException) return [appointment]
        return segments.map((segment) => ({ ...appointment, occurrenceHour: segment.hour, school_hours: [segment.hour], time_segments: [segment], starts_at: segment.starts_at, ends_at: segment.ends_at }))
    }).sort((left, right) => left.occurrenceDate.localeCompare(right.occurrenceDate) || left.starts_at.localeCompare(right.starts_at))
}
