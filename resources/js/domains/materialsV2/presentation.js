export const allCategoriesValue = '__all_categories__'
export const reminderCategoryName = 'Termine'
export const screenshotCategoryName = 'Screenshots'
export const linkCategoryName = 'Links'
export const fileCategoryName = 'Dateien'
export const noteCategoryName = 'Notizen'

function normalizedCategory(category) {
    return String(category || '').trim().toLocaleLowerCase('de-AT')
}

function normalizedSearchText(value) {
    return String(value || '')
        .normalize('NFKD')
        .replace(/\p{Diacritic}/gu, '')
        .replace(/ß/gu, 'ss')
        .toLocaleLowerCase('de-AT')
        .replace(/[^\p{L}\p{N}]+/gu, ' ')
        .trim()
        .replace(/\s+/gu, ' ')
}

function editDistance(firstValue, secondValue) {
    const first = Array.from(firstValue)
    const second = Array.from(secondValue)
    const distances = Array.from(
        { length: first.length + 1 },
        (_, row) => Array.from({ length: second.length + 1 }, (_, column) => (row === 0 ? column : row)),
    )

    for (let row = 1; row <= first.length; row += 1) {
        for (let column = 1; column <= second.length; column += 1) {
            const substitutionCost = first[row - 1] === second[column - 1] ? 0 : 1
            distances[row][column] = Math.min(
                distances[row - 1][column] + 1,
                distances[row][column - 1] + 1,
                distances[row - 1][column - 1] + substitutionCost,
            )

            if (
                row > 1
                && column > 1
                && first[row - 1] === second[column - 2]
                && first[row - 2] === second[column - 1]
            ) {
                distances[row][column] = Math.min(
                    distances[row][column],
                    distances[row - 2][column - 2] + substitutionCost,
                )
            }
        }
    }

    return distances[first.length][second.length]
}

export function fuzzyTextMatch(value, query) {
    const normalizedValue = normalizedSearchText(value)
    const normalizedQuery = normalizedSearchText(query)

    if (normalizedQuery === '') {
        return true
    }

    const compactValue = normalizedValue.replaceAll(' ', '')
    const compactQuery = normalizedQuery.replaceAll(' ', '')

    if (compactValue.includes(compactQuery)) {
        return true
    }

    if (compactQuery.length < 3) {
        return false
    }

    const allowedDistance = compactQuery.length <= 4
        ? 1
        : compactQuery.length <= 10
            ? 2
            : 3
    const comparableValues = [compactValue, ...normalizedValue.split(' ')]

    return comparableValues.some((candidate) => {
        const lengthDifference = Math.abs(candidate.length - compactQuery.length)
        if (
            lengthDifference <= allowedDistance
            && editDistance(candidate, compactQuery) <= allowedDistance
        ) {
            return true
        }

        if (candidate.length < compactQuery.length) {
            return false
        }

        return editDistance(candidate.slice(0, compactQuery.length), compactQuery) <= allowedDistance
    })
}

export function isReminderCategory(category) {
    return normalizedCategory(category) === reminderCategoryName.toLocaleLowerCase('de-AT')
}

export function isScreenshotCategory(category) {
    return normalizedCategory(category) === screenshotCategoryName.toLocaleLowerCase('de-AT')
}

export function isLinkCategory(category) {
    return normalizedCategory(category) === linkCategoryName.toLocaleLowerCase('de-AT')
}

export function isFileCategory(category) {
    return normalizedCategory(category) === fileCategoryName.toLocaleLowerCase('de-AT')
}

export function isNoteCategory(category) {
    return normalizedCategory(category) === noteCategoryName.toLocaleLowerCase('de-AT')
}

export function isDefaultCategory(category) {
    return isReminderCategory(category)
        || isScreenshotCategory(category)
        || isLinkCategory(category)
        || isFileCategory(category)
        || isNoteCategory(category)
}

export function supportsDocumentProcessing(category) {
    return isFileCategory(category) || !isDefaultCategory(category)
}

export function categoryIcon(category) {
    if (isReminderCategory(category)) {
        return 'mdi-calendar-clock-outline'
    }

    if (isScreenshotCategory(category)) {
        return 'mdi-monitor-screenshot'
    }

    if (isNoteCategory(category)) {
        return 'mdi-note-text-outline'
    }

    if (isFileCategory(category)) {
        return 'mdi-file-multiple-outline'
    }

    return isLinkCategory(category) ? 'mdi-link-variant' : 'mdi-shape-outline'
}

export function createActionIcon(category) {
    if (isReminderCategory(category)) {
        return 'mdi-calendar-plus'
    }

    if (isScreenshotCategory(category)) {
        return 'mdi-image-plus-outline'
    }

    if (isNoteCategory(category)) {
        return 'mdi-note-text-outline'
    }

    if (isFileCategory(category)) {
        return 'mdi-file-plus-outline'
    }

    return isLinkCategory(category) ? 'mdi-link-plus' : 'mdi-plus'
}

export function createActionLabel(category, fallback) {
    if (isReminderCategory(category)) {
        return 'Termin hinzufügen'
    }

    if (isScreenshotCategory(category)) {
        return 'Screenshot hinzufügen'
    }

    if (isNoteCategory(category)) {
        return 'Notiz hinzufügen'
    }

    if (isFileCategory(category)) {
        return 'Dateien hinzufügen'
    }

    return isLinkCategory(category) ? 'Link hinzufügen' : fallback
}

export function statusMeta(status) {
    return {
        pending: { label: 'Wartet', color: 'info', icon: 'mdi-clock-outline' },
        processing: { label: 'Wird analysiert', color: 'info', icon: 'mdi-progress-clock' },
        ready: { label: 'Bereit', color: 'success', icon: 'mdi-check-circle-outline' },
        partial: { label: 'Teilweise gelesen', color: 'warning', icon: 'mdi-alert-circle-outline' },
        failed: { label: 'Fehlgeschlagen', color: 'error', icon: 'mdi-alert-outline' },
    }[status] || { label: status || 'Unbekannt', color: 'default', icon: 'mdi-help-circle-outline' }
}

export function keywordStatusMeta(status) {
    return {
        pending: { label: 'Wartet auf Analyse', color: 'info' },
        processing: { label: 'Tags werden ermittelt', color: 'info' },
        ready: { label: 'Tags erkannt', color: 'success' },
        empty: { label: 'Keine relevanten Tags', color: 'warning' },
        skipped: { label: 'Nicht auswertbar', color: 'warning' },
        failed: { label: 'Tag-Erkennung fehlgeschlagen', color: 'error' },
    }[status] || { label: status || 'Noch nicht verarbeitet', color: 'default' }
}

export function attachmentIcon(attachment) {
    const mimeType = String(attachment.mime_type || '').toLowerCase()
    const name = String(attachment.original_name || '').toLowerCase()

    if (mimeType.includes('pdf') || name.endsWith('.pdf')) return 'mdi-file-pdf-box'
    if (mimeType.includes('word') || name.endsWith('.docx')) return 'mdi-file-word-outline'
    if (mimeType.includes('sheet') || name.endsWith('.xlsx')) return 'mdi-file-excel-outline'
    if (mimeType.includes('presentation') || name.endsWith('.pptx')) return 'mdi-file-powerpoint-outline'
    if (mimeType.startsWith('image/')) return 'mdi-file-image-outline'

    return 'mdi-file-document-outline'
}

export function formatFileSize(bytes) {
    const size = Number(bytes || 0)
    if (size < 1024) return `${size} B`
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`

    return `${(size / (1024 * 1024)).toFixed(1)} MB`
}

export function formatDateTime(value) {
    const date = new Date(value)

    return Number.isNaN(date.getTime())
        ? ''
        : new Intl.DateTimeFormat('de-AT', {
            dateStyle: 'short',
            timeStyle: 'short',
        }).format(date)
}

export function reminderDate(value) {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/u)
    if (!match) {
        return null
    }

    const [, year, month, day] = match
    const date = new Date(Number(year), Number(month) - 1, Number(day))

    return Number.isNaN(date.getTime()) ? null : date
}

export function formatReminderDate(value) {
    const date = reminderDate(value)

    return date
        ? new Intl.DateTimeFormat('de-AT', {
            weekday: 'short',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        }).format(date)
        : ''
}

export function reminderBadge(value, todayValue = new Date()) {
    const date = reminderDate(value)
    if (!date) {
        return null
    }

    const today = new Date(todayValue)
    today.setHours(0, 0, 0, 0)
    const differenceInDays = Math.round((date.getTime() - today.getTime()) / 86400000)

    if (differenceInDays < 0) {
        return { label: 'Vergangen', color: 'default' }
    }

    if (differenceInDays === 0) {
        return { label: 'Heute', color: 'error' }
    }

    if (differenceInDays === 1) {
        return { label: 'Morgen', color: 'warning' }
    }

    return null
}
