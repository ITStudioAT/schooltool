export function nibbleConversion(value, sourceBase) {
    const input = String(value).trim().replace(/\s/g, '').toUpperCase()
    if (![2, 16].includes(sourceBase)) return null
    const digits = sourceBase === 16 ? input.replace(/^#/, '') : input
    if (!(sourceBase === 2 ? /^[01]{1,16}$/ : /^[0-9A-F]{1,4}$/).test(digits)) return null

    const binary = sourceBase === 2 ? digits : [...digits].map(digit => Number.parseInt(digit, 16).toString(2).padStart(4, '0')).join('')
    const padding = sourceBase === 2 ? (4 - binary.length % 4) % 4 : 0
    const paddedBinary = binary.padStart(binary.length + padding, '0')
    const groups = paddedBinary.match(/.{4}/g).map((bits, index) => ({
        binary: bits,
        original: index === 0 ? bits.slice(padding) : bits,
        hex: Number.parseInt(bits, 2).toString(16).toUpperCase(),
        value: Number.parseInt(bits, 2),
    }))

    return { source: digits, binary: paddedBinary, hex: groups.map(group => group.hex).join(''), padding, groups }
}

export const nibbleReference = Array.from({ length: 16 }, (_, value) => ({
    binary: value.toString(2).padStart(4, '0'), hex: value.toString(16).toUpperCase(), value,
}))
