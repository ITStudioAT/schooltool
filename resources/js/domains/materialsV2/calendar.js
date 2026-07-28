export function parseCalendarDate(value, fallbackDate = new Date()) {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/u)
    if (!match) {
        return new Date(fallbackDate)
    }

    const [, year, month, day] = match
    const date = new Date(Number(year), Number(month) - 1, Number(day))

    return Number.isNaN(date.getTime()) ? new Date(fallbackDate) : date
}

export function isCalendarDate(value) {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/u)
    if (!match) {
        return false
    }

    const [, year, month, day] = match
    const date = new Date(Number(year), Number(month) - 1, Number(day))

    return date.getFullYear() === Number(year)
        && date.getMonth() === Number(month) - 1
        && date.getDate() === Number(day)
}

export function formatCalendarDate(date) {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

export function startOfCalendarWeek(date) {
    const start = new Date(date)
    const daysSinceMonday = (start.getDay() + 6) % 7

    start.setDate(start.getDate() - daysSinceMonday)
    start.setHours(0, 0, 0, 0)

    return start
}

export function addCalendarDays(date, days) {
    const result = new Date(date)

    result.setDate(result.getDate() + days)

    return result
}

export function calendarVisibleRange(focusDate, displayMode) {
    if (displayMode === 'week') {
        const start = startOfCalendarWeek(focusDate)

        return {
            start,
            end: addCalendarDays(start, 6),
        }
    }

    const monthStart = new Date(focusDate.getFullYear(), focusDate.getMonth(), 1)
    const monthEnd = new Date(focusDate.getFullYear(), focusDate.getMonth() + 1, 0)

    return {
        start: startOfCalendarWeek(monthStart),
        end: addCalendarDays(startOfCalendarWeek(monthEnd), 6),
    }
}

export function buildCalendarDays({ focusDate, range, items, today = new Date() }) {
    const todayKey = formatCalendarDate(today)
    const itemsByDate = items.reduce((groupedItems, item) => {
        if (item.reminder_date) {
            groupedItems[item.reminder_date] ||= []
            groupedItems[item.reminder_date].push(item)
        }

        return groupedItems
    }, {})
    const days = []

    for (
        let date = new Date(range.start);
        date <= range.end;
        date = addCalendarDays(date, 1)
    ) {
        const key = formatCalendarDate(date)

        days.push({
            key,
            dayNumber: date.getDate(),
            weekday: new Intl.DateTimeFormat('de-AT', { weekday: 'short' }).format(date),
            isCurrentMonth: date.getMonth() === focusDate.getMonth(),
            isToday: key === todayKey,
            items: [...(itemsByDate[key] || [])].sort(compareReminderItems),
        })
    }

    return days
}

export function calendarPeriodLabel(focusDate, displayMode, range) {
    if (displayMode === 'month') {
        return new Intl.DateTimeFormat('de-AT', {
            month: 'long',
            year: 'numeric',
        }).format(focusDate)
    }

    const startLabel = new Intl.DateTimeFormat('de-AT', {
        day: 'numeric',
        month: range.start.getMonth() === range.end.getMonth() ? undefined : 'long',
    }).format(range.start)
    const endLabel = new Intl.DateTimeFormat('de-AT', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(range.end)

    return `${startLabel} – ${endLabel}`
}

export function moveCalendarDate(value, displayMode, direction) {
    const focusDate = parseCalendarDate(value)

    if (displayMode === 'month') {
        const day = focusDate.getDate()

        focusDate.setDate(1)
        focusDate.setMonth(focusDate.getMonth() + direction)
        const lastDayOfTargetMonth = new Date(focusDate.getFullYear(), focusDate.getMonth() + 1, 0).getDate()

        focusDate.setDate(Math.min(day, lastDayOfTargetMonth))
    } else {
        focusDate.setDate(focusDate.getDate() + (direction * 7))
    }

    return formatCalendarDate(focusDate)
}

export function compareReminderItems(left, right) {
    const timeComparison = String(left.reminder_time || '').localeCompare(String(right.reminder_time || ''))

    return timeComparison !== 0 ? timeComparison : String(left.title || '').localeCompare(String(right.title || ''), 'de-AT')
}

export function calendarEventTitle(item) {
    return `${item.reminder_time || 'Ganztägig'} · ${item.title}`
}
