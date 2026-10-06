function minorValue(value) {
    const match = String(value ?? '').trim().match(/^(-?)(\d+)(?:[.,](\d{1,2}))?$/)
    if (!match) return null
    const amount = Number(match[2]) * 100 + Number((match[3] || '').padEnd(2, '0'))

    return Number.isSafeInteger(amount) ? (match[1] ? -amount : amount) : null
}

function shortReason(reason) {
    const questions = String(reason || '').match(/Fragen?\s+\d+(?:\s*(?:,|und)\s*\d+)*\s+(?:falsch|nicht beantwortet)/i)
    if (questions) return questions[0]
    const clauses = String(reason || '').replace(/\([^)]*\)/g, '').split(/;|\.\s+/)
        .map(clause => clause.trim().replace(/\.$/, '').trim()).filter(Boolean)
    const error = clauses.find(clause => /fehlt|fehlend|fehler|statt|muss|unvollständig|falsch|kleingeschrieben|großgeschrieben/i.test(clause))
        || clauses[0] || ''
    if (error.length <= 110) return error

    return error.slice(0, 107).replace(/\s+\S*$/, '') + '…'
}

function legacyRecord(comment) {
    const criteria = []
    const adjustments = []
    for (const line of comment.split(/\r?\n/).filter(line => line.trim())) {
        const criterion = line.match(/^([^:\n]+):\s*(\d+(?:[.,]\d{1,2})?)\s*\/\s*(\d+(?:[.,]\d{1,2})?) Punkte\.\s*(.+)$/)
        const adjustment = line.match(/^([^:\n]+):\s*(-?\d+(?:[.,]\d{1,2})?) Punkte\.\s*(.+)$/)
        if (criterion) {
            criteria.push({ criterion: criterion[1], earned_minor: minorValue(criterion[2]), maximum_minor: minorValue(criterion[3]), reason: criterion[4] })
        } else if (adjustment) {
            adjustments.push({ label: adjustment[1], amount_minor: minorValue(adjustment[2]), reason: adjustment[3] })
        } else {
            return null
        }
    }
    if (!criteria.length) return null

    return { evaluation_state: 'complete', criteria, adjustments,
        total_minor: criteria.reduce((sum, criterion) => sum + criterion.earned_minor, 0)
            + adjustments.reduce((sum, adjustment) => sum + adjustment.amount_minor, 0) }
}

export function assessmentWrongQuestions(text) {
    const correction = String(text || '').split('Multiple-Choice-Korrektur')[1]
    if (!correction) return null
    const expected = correction.match(/\b(\d+)\s+falsch\b/)
    const questions = [...new Set([...correction.matchAll(/\b(\d+)\s*·\s*Katalog\s+\d+/g)].map(match => Number(match[1])))]
    if (!expected || Number(expected[1]) !== questions.length || !questions.length) return null

    return questions
}

export function assessmentDeductionComment(comment, record = null, grade = null, wrongQuestions = null) {
    const original = String(comment || '').trim()
    const source = record?.comment?.trim() === original ? record : legacyRecord(original)
    if (!source || source.evaluation_state !== 'complete') return original
    const currentPoints = minorValue(grade)
    if (currentPoints !== null && currentPoints !== source.total_minor) return original
    const deductions = []
    for (const criterion of source.criteria || []) {
        const { maximum_minor: maximum, earned_minor: earned } = criterion
        if (!Number.isSafeInteger(maximum) || !Number.isSafeInteger(earned) || earned < 0 || earned > maximum) return original
        if (earned < maximum) deductions.push({ label: criterion.criterion, amount: maximum - earned, reason: criterion.reason })
    }
    for (const adjustment of source.adjustments || []) {
        if (!Number.isSafeInteger(adjustment.amount_minor)) return original
        if (adjustment.amount_minor < 0) deductions.push({ label: adjustment.label, amount: -adjustment.amount_minor, reason: adjustment.reason })
    }

    return deductions.map(deduction => {
        const amount = `${Math.trunc(deduction.amount / 100)},${String(deduction.amount % 100).padStart(2, '0')}`

        const multipleChoice = /multiple.choice/i.test(deduction.label)
        const reason = multipleChoice && wrongQuestions?.length
            ? `Fragen ${wrongQuestions.join(', ')} falsch`
            : shortReason(deduction.reason)

        return `${deduction.label} −${amount}${multipleChoice ? ' Punkte' : ''}: ${reason}`
    }).join('\n')
}
