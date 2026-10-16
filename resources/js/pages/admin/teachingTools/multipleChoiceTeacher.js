export function newQuestion() {
    return {
        id: crypto.randomUUID(),
        prompt: '',
        type: 'single',
        options: [newOption(), newOption()],
        correct_option_ids: [],
        active: true,
        reviewed: false,
    }
}

export function newOption() {
    return { id: crypto.randomUUID(), text: '' }
}

export function detectImportFormat(text, { name = '', type = '' } = {}) {
    const extension = name.toLowerCase().match(/\.([^.]+)$/)?.[1] ?? ''
    const mime = type.toLowerCase().split(';')[0].trim()
    const jsonMime = ['application/json', 'text/json'].includes(mime)
    const textMime = ['text/plain', 'text/markdown', 'text/x-markdown'].includes(mime)
    if ((extension && !['md', 'txt', 'json'].includes(extension))
        || (mime && !jsonMime && !textMime && mime !== 'application/octet-stream')) {
        throw new Error('Bitte wähle eine Markdown-, Text- oder JSON-Datei (.md, .txt, .json).')
    }
    const source = text.replace(/^\uFEFF/, '').trim()
    if (!source) {
        if (name) {
            throw new Error('Die ausgewählte Datei ist leer.')
        }
        return null
    }
    if (/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/.test(source)) {
        throw new Error('Die Datei enthält keine lesbare Textvorlage. Bitte verwende UTF-8-Text, Markdown oder JSON.')
    }
    try {
        JSON.parse(source)
        return 'json'
    } catch {
        if (extension === 'json' || jsonMime || /^\s*(?:\{|\[\s*(?:\{|\[|"|\]))/.test(source)) {
            throw new Error('Die Vorlage sieht wie JSON aus, enthält aber kein gültiges JSON. Bitte prüfe den Inhalt.')
        }
    }
    if (name && !extension && !jsonMime && !textMime
        && !/^\s*(?:#{1,6}\s+(?:Frage\s*)?\d+\s*[.:)\-]|\d+\s*[.)]\s+)/imu.test(source)) {
        throw new Error('Das Dateiformat ist nicht eindeutig. Bitte verwende eine Datei mit .md, .txt oder .json.')
    }
    return 'markdown'
}

export function questionProblems(question) {
    const problems = []
    const options = question.options ?? []
    const correct = question.correct_option_ids ?? []

    if (!String(question.prompt ?? '').trim()) {
        problems.push('Fragetext fehlt.')
    }
    if (options.length < 2 || options.some((option) => !String(option.text ?? '').trim())) {
        problems.push('Mindestens zwei vollständig ausgefüllte Antworten sind erforderlich.')
    }
    const normalizedTexts = options.map((option) => String(option.text ?? '').trim().replace(/\s+/g, ' ').toLocaleLowerCase('de-AT'))
    if (new Set(normalizedTexts).size !== normalizedTexts.length) {
        problems.push('Antworttexte dürfen nicht doppelt vorkommen.')
    }
    const optionIds = options.map((option) => option.id)
    if (optionIds.some((id) => typeof id !== 'string' || !id.trim()) || new Set(optionIds).size !== optionIds.length) {
        problems.push('Die Antwortoptionen benötigen eindeutige Kennungen. Die betroffenen Antworten bitte neu anlegen.')
    }
    if (!correct.length || new Set(correct).size !== correct.length || correct.some((id) => !options.some((option) => option.id === id))) {
        problems.push('Richtige Antworten festlegen.')
    }
    if (question.type === 'single' && correct.length !== 1) {
        problems.push('Bei Einfachauswahl genau eine richtige Antwort festlegen.')
    }
    if (question.type === 'multiple' && correct.length < 2) {
        problems.push('Bei Mehrfachauswahl mindestens zwei richtige Antworten festlegen.')
    }
    if (!['single', 'multiple'].includes(question.type)) {
        problems.push('Antworttyp auswählen.')
    }

    return problems
}

export function readyQuestions(questions) {
    return questions.filter((question) => question.active && question.reviewed && !questionProblems(question).length)
}

export function messageText(message) {
    if (typeof message === 'string') {
        return message
    }

    return message?.message ?? message?.error ?? JSON.stringify(message)
}

export function requestError(error) {
    const errors = error.response?.data?.errors

    if (errors && !Array.isArray(errors)) {
        return Object.values(errors).flat().map(messageText).join(' ')
    }

    return error.response?.data?.message ?? 'Die Verbindung ist fehlgeschlagen. Bitte erneut versuchen.'
}

export function statusLabel(status) {
    return {
        not_started: 'Noch nicht begonnen',
        ready: 'Bereit',
        in_progress: 'In Bearbeitung',
        submitted: 'Abgegeben',
        timeout: 'Durch Zeitablauf abgeschlossen',
    }[status] ?? status
}
