const notationByBase = {
    2: { prefix: '', subscript: '2', unicodeSubscript: '₂' },
    10: { prefix: '', subscript: '', unicodeSubscript: '' },
    16: { prefix: '#', subscript: '', unicodeSubscript: '' },
}

export function numberSystemNotation(base) {
    return notationByBase[base] || notationByBase[10]
}

// Keep saved puzzle values and accepted answers intact; format only displayed text.
export function formatNumberSystemText(value) {
    return String(value ?? '').replace(/\b([01]+(?:[ \t]+[01]+)*)\s*\(dual\)/gi, (_, digits) => `${digits}${numberSystemNotation(2).unicodeSubscript}`)
}
