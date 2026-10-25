export const maximumDecimalExample = 65535

export function parseDecimalExample(value) {
    const text = String(value).trim()
    if (!/^\d+$/.test(text)) return null
    const number = Number(text)
    return Number.isInteger(number) && number >= 0 && number <= maximumDecimalExample ? number : null
}

export function decimalDivisionSteps(number) {
    return radixDivisionSteps(number, 2)
}

export function radixDivisionSteps(number, base) {
    if (parseDecimalExample(number) !== number) throw new RangeError('Ganze Zahl von 0 bis 65535 erforderlich.')
    if (![2, 16].includes(base)) throw new RangeError('Unterstützte Basis: 2 oder 16.')
    const rows = []
    let dividend = number
    do {
        const quotient = Math.floor(dividend / base)
        rows.push({ dividend, quotient, remainder: dividend % base, place: base ** rows.length })
        dividend = quotient
    } while (dividend > 0)
    return rows
}
