const DEFAULT_CATEGORY_EVALUATION_VALUE_ITEMS = [
    { value: 'Keine Bewertung', color: '#b0bec5' },
    { value: 'Offen', color: '#fb8c00' },
    { value: 'Bestanden', color: '#43a047' },
    { value: '1', color: '#2e7d32' },
    { value: '2', color: '#7cb342' },
    { value: '3', color: '#f9a825' },
    { value: '4', color: '#ef6c00' },
    { value: '5', color: '#e53935' },
    { value: 'Nicht bestanden', color: '#c62828' },
]

const DEFAULT_CATEGORY_EVALUATION_COLORS = DEFAULT_CATEGORY_EVALUATION_VALUE_ITEMS.reduce((carry, item) => {
    carry[item.value] = item.color
    return carry
}, {})

export function defaultTeachingCategoryEvaluationValueItems() {
    return DEFAULT_CATEGORY_EVALUATION_VALUE_ITEMS.map((item) => ({ ...item }))
}

export function normalizeTeachingCategoryEvaluationColor(value, fallback = '') {
    const raw = String(value ?? '').trim()
    if (!raw) {
        return fallback
    }

    const normalized = raw.startsWith('#') ? raw : `#${raw}`
    if (!/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(normalized)) {
        return fallback
    }

    if (normalized.length === 4) {
        return `#${normalized[1]}${normalized[1]}${normalized[2]}${normalized[2]}${normalized[3]}${normalized[3]}`.toLowerCase()
    }

    return normalized.toLowerCase()
}

export function normalizeTeachingCategoryEvaluationValueItems(values, options = {}) {
    const fallbackToDefaults = options?.fallbackToDefaults !== false
    const source = Array.isArray(values) && values.length
        ? values
        : (fallbackToDefaults ? DEFAULT_CATEGORY_EVALUATION_VALUE_ITEMS : [])

    const normalizedItems = []
    const seen = new Set()

    source.forEach((item) => {
        const value = typeof item === 'object' && item !== null
            ? String(item.value ?? '').trim()
            : String(item ?? '').trim()

        if (!value) {
            return
        }

        const key = value.toLocaleLowerCase()
        if (seen.has(key)) {
            return
        }
        seen.add(key)

        const fallbackColor = DEFAULT_CATEGORY_EVALUATION_COLORS[value] || '#4f6fb3'
        const rawColor = typeof item === 'object' && item !== null ? item.color : ''

        normalizedItems.push({
            value,
            color: normalizeTeachingCategoryEvaluationColor(rawColor, fallbackColor),
        })
    })

    if (!normalizedItems.length && fallbackToDefaults) {
        return defaultTeachingCategoryEvaluationValueItems()
    }

    return normalizedItems
}

export function teachingCategoryEvaluationValueLabels(values, options = {}) {
    return normalizeTeachingCategoryEvaluationValueItems(values, options).map((item) => item.value)
}

export function teachingCategoryEvaluationColorForValue(values, selectedValue, fallback = '') {
    const value = String(selectedValue ?? '').trim()
    if (!value) {
        return fallback
    }

    const item = normalizeTeachingCategoryEvaluationValueItems(values).find((entry) => entry.value === value)
    if (item?.color) {
        return item.color
    }

    return normalizeTeachingCategoryEvaluationColor(fallback, DEFAULT_CATEGORY_EVALUATION_COLORS[value] || '')
}
