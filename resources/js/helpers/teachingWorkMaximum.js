export function requiresWorkMaximumPlus(course, type) {
    const definition = course?.teaching_entry_area?.entry_definitions?.find((entry) => entry.category === 'Benotung' && entry.short_name === type)
    return definition?.properties_mode === 'plus' && Boolean(definition.allows_maximum_plus)
}

export function workMaximumPlusError(value) {
    return typeof value !== 'boolean' && /^\d+$/.test(String(value ?? '').trim())
        && Number.isInteger(Number(value)) && Number(value) > 0 && Number(value) <= 4294967295
        ? '' : 'Bitte eine maximale Plusanzahl als positive ganze Zahl eingeben.'
}
