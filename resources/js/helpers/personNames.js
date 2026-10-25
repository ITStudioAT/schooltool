const names = new Intl.Collator('de-AT', { sensitivity: 'base', numeric: true })

function person(row) {
    return row?.identity || (typeof row?.person === 'object' ? row.person : row) || {}
}

export function comparePersonNames(first, second) {
    const a = person(first)
    const b = person(second)
    return names.compare(a.last_name ?? a._last ?? '', b.last_name ?? b._last ?? '')
        || names.compare(a.first_name ?? a._first ?? '', b.first_name ?? b._first ?? '')
        || names.compare(a.schoolclass ?? a.class_name ?? a._class ?? '', b.schoolclass ?? b.class_name ?? b._class ?? '')
}

export function sortedPeople(rows = []) {
    return [...rows].sort(comparePersonNames)
}
