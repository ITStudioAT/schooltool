import { standardPercentageGrades } from './gradeCalculation'

function fraction(numerator, denominator = 1n) {
    let left = numerator < 0n ? -numerator : numerator
    let right = denominator
    while (right) [left, right] = [right, left % right]
    return { numerator: numerator / (left || 1n), denominator: denominator / (left || 1n) }
}

function decimal(value) {
    const text = String(value ?? '').trim().replace(',', '.')
    if (!/^-?\d+(?:\.\d+)?$/.test(text)) return null
    const decimals = text.split('.')[1]?.length || 0
    return fraction(BigInt(text.replace('.', '')), 10n ** BigInt(decimals))
}

function add(left, right) {
    return fraction(left.numerator * right.denominator + right.numerator * left.denominator,
        left.denominator * right.denominator)
}

function sum(values) {
    return values.reduce(add, fraction(0n))
}

function compare(left, right) {
    const difference = left.numerator * right.denominator - right.numerator * left.denominator
    return difference < 0n ? -1 : difference > 0n ? 1 : 0
}

function display(value) {
    if (value.numerator < 0n) return `−${display(fraction(-value.numerator, value.denominator))}`
    let denominator = value.denominator
    while (denominator % 2n === 0n) denominator /= 2n
    while (denominator % 5n === 0n) denominator /= 5n
    if (denominator !== 1n) return `${value.numerator}/${value.denominator}`
    let remainder = value.numerator % value.denominator
    let result = String(value.numerator / value.denominator)
    if (remainder) result += ','
    while (remainder) {
        remainder *= 10n
        result += String(remainder / value.denominator)
        remainder %= value.denominator
    }
    return result
}

export function simulationPropertyMode(entry) {
    if (entry.properties_mode !== 'fixed') return entry.properties_mode
    return entry.fixed_properties?.length === 5 && ['1', '2', '3', '4', '5'].every((grade) => entry.fixed_properties.includes(grade))
        ? 'grades' : 'free'
}

export function isStoredSimulationValue(entry, value) {
    if (typeof value !== 'string' || !value.trim() || value.length > 50) return false
    if (!entry.has_properties) return value === 'Eintrag'
    const mode = simulationPropertyMode(entry)
    if (mode === 'grades') return /^[1-5]$/.test(value)
    if (mode === 'points') return /^\d+(?:[.,]\d+)?$/.test(value)
    if (mode === 'plus') return /^\+{1,50}$/.test(value)
    if (mode === 'plus_minus') return /^(?:\+{1,50}|[-−]{1,50}|0|~)$/.test(value)
    const options = entry.fixed_properties || []
    return options.length ? options.map(String).includes(value) : true
}

export function simulationPointsResult(entries, valuesByEntry) {
    const values = []
    let maximum = fraction(0n)
    for (const entry of entries) {
        const rawValues = valuesByEntry[entry.id] || []
        if (!rawValues.length) continue
        if (entry.properties_mode !== 'points') return null
        const entryMaximum = decimal(entry.maximum_points)
        if (!entryMaximum || entryMaximum.numerator <= 0n) return null
        for (const raw of rawValues) {
            const value = decimal(raw)
            if (!value || value.numerator < 0n || compare(value, entryMaximum) > 0) return null
            values.push(value)
            maximum = add(maximum, entryMaximum)
        }
    }
    if (!values.length) return null
    const achieved = sum(values)
    // Compare exact ratios against the shared fixed percentage scale.
    const percent = fraction(achieved.numerator * maximum.denominator * 100n,
        achieved.denominator * maximum.numerator)
    const percentage = display(percent)
    return { achieved: display(achieved), maximum: display(maximum), percentage: percentage.includes('/') ? `≈ ${displayRounded(percent)}` : percentage, grade: String(standardPercentageGrades.find((band) => compare(percent, decimal(band.min)) >= 0)?.grade ?? 5) }
}

function pointsResult(entries, valuesByEntry) {
    return simulationPointsResult(entries, valuesByEntry)?.grade ?? null
}

function displayRounded(value) {
    return display(fraction((value.numerator * 10000n * 2n + value.denominator) / (value.denominator * 2n), 10000n))
}

function displayExpansion(value) {
    const exact = display(value)
    if (!exact.includes('/')) return exact
    let remainder = value.numerator % value.denominator
    let result = `${value.numerator / value.denominator},`
    for (let index = 0; index < 6; index++) {
        remainder *= 10n
        result += String(remainder / value.denominator)
        remainder %= value.denominator
    }
    return `${result}…`
}

function parseResult(value) {
    if (value === null) return null
    if (/^\d+\/\d+$/.test(value)) {
        const [numerator, denominator] = value.split('/').map(BigInt)
        return fraction(numerator, denominator)
    }
    return decimal(value)
}

export function simulationSignBalance(part, valuesByEntry) {
    const values = (part.entries || []).flatMap((entry) => valuesByEntry[entry.id] || [])
    if (!values.length) return null
    let balance = 0n
    for (const raw of values) {
        const value = raw.replaceAll('−', '-')
        if (!/^(?:\+{1,50}|-{1,50}|0|~)$/.test(value)) return null
        balance += value === '~' ? 1n : value === '0' ? 0n : BigInt(value.length) * (value[0] === '+' ? 2n : -2n)
    }
    return fraction(balance, 2n)
}

export function simulationStructure(parts, groups, rootWeights, valuesByEntry) {
    const absentOptional = Symbol('absent optional standard grade')
    const partResults = Object.fromEntries(parts.map((part) => [part.gradingPartId ?? part.id,
        ['grade_each', 'grade_mean'].includes(part.points_assessment_mode) && !(part.is_required ?? false)
            && part.entries?.length && part.entries.every((entry) => entry.has_properties && simulationPropertyMode(entry) === 'grades')
            && (part.entries || []).every((entry) => !(valuesByEntry[entry.id] || []).length)
            ? absentOptional : parseResult(simulationPartGrade(part, valuesByEntry))]))
    const groupResults = {}
    const groupSteps = {}
    const partSteps = {}
    let rootStep = ''
    const resolve = (context, visited = []) => {
        if (visited.includes(context)) return null
        const children = new Map()
        for (const group of groups.filter((group) => (group.parent_group_id ?? 'root') === context)) {
            children.set(`group:${group.id}`, resolve(group.id, [...visited, context]))
        }
        const direct = parts.filter((part) => (groups.find((group) => group.part_ids.includes(part.gradingPartId ?? part.id))?.id ?? 'root') === context)
        for (const part of direct) children.set(`part:${part.gradingPartId ?? part.id}`, partResults[part.gradingPartId ?? part.id])
        const adjustments = direct.filter((part) => part.points_assessment_mode === 'sign_adjust')
        let result = null
        let step = ''
        if (adjustments.length) {
            if (adjustments.length === 1 && children.size === 2) {
                const part = adjustments[0]
                const id = part.gradingPartId ?? part.id
                children.delete(`part:${id}`)
                const base = [...children.values()][0]
                const balance = simulationSignBalance(part, valuesByEntry)
                    ?? (!part.is_required && (part.entries || []).every((entry) => !(valuesByEntry[entry.id] || []).length) ? fraction(0n) : null)
                const configuration = part.sign_adjustment
                const fields = ['improvement_factor', 'max_improvement', 'deterioration_factor', 'max_deterioration']
                const valid = configuration && Object.keys(configuration).length === 4 && fields.every((field) => {
                    const value = decimal(configuration[field])
                    return value && value.numerator >= 0n
                })
                if (base && base !== absentOptional && balance && valid && compare(base, fraction(1n)) >= 0 && compare(base, fraction(5n)) <= 0) {
                    const positive = balance.numerator >= 0n
                    const factor = decimal(configuration[positive ? 'improvement_factor' : 'deterioration_factor'])
                    const cap = decimal(configuration[positive ? 'max_improvement' : 'max_deterioration'])
                    const amount = fraction((balance.numerator < 0n ? -balance.numerator : balance.numerator) * factor.numerator, balance.denominator * factor.denominator)
                    const limited = compare(amount, cap) > 0 ? cap : amount
                    const raw = add(base, fraction(limited.numerator * (positive ? -1n : 1n), limited.denominator))
                    result = compare(raw, fraction(1n)) < 0 ? fraction(1n) : compare(raw, fraction(5n)) > 0 ? fraction(5n) : raw
                    partResults[id] = result
                    step = `Basisnote ${display(base)}; Saldo ${display(balance)} × ${display(factor)} = ${display(amount)}${compare(amount, limited) !== 0 ? `; angewandt ${display(limited)}` : ''}. ${display(base)} ${positive ? '−' : '+'} ${display(limited)} = ${display(raw)}${compare(raw, result) !== 0 ? ` → Note ${display(result)}` : ''}`
                    partSteps[id] = step
                }
            }
        } else {
            const weights = context === 'root' ? rootWeights : groups.find((group) => group.id === context)?.weights
            if (context === 'root' && !weights && children.size === 1) result = [...children.values()][0]
            else if (weights?.length === children.size && children.size) {
                const seen = new Set()
                const terms = []
                const factors = []
                for (const row of weights) {
                    const key = row.group_id ? `group:${row.group_id}` : `part:${row.part_id}`
                    const grade = children.get(key)
                    const weight = decimal(row.weight)
                    if (seen.has(key) || !grade || !weight || weight.numerator <= 0n) return null
                    seen.add(key)
                    if (grade === absentOptional) continue
                    factors.push(weight)
                    terms.push(fraction(grade.numerator * weight.numerator, grade.denominator * weight.denominator))
                }
                if (!terms.length) {
                    if (context !== 'root') {
                        groupResults[context] = null
                        groupSteps[context] = ''
                    }
                    return absentOptional
                }
                const usedWeights = weights.filter((row) => children.get(row.group_id ? `group:${row.group_id}` : `part:${row.part_id}`) !== absentOptional)
                const total = sum(terms)
                const weightsTotal = sum(factors)
                result = fraction(total.numerator * weightsTotal.denominator, total.denominator * weightsTotal.numerator)
                step = `(${usedWeights.map((row) => `${display(children.get(row.group_id ? `group:${row.group_id}` : `part:${row.part_id}`))} × ${display(decimal(row.weight))}`).join(' + ')}) / ${display(weightsTotal)} = ${display(result)}`
                if (context === 'root') {
                    const operands = usedWeights.map((row) => {
                        const grade = display(children.get(row.group_id ? `group:${row.group_id}` : `part:${row.part_id}`))
                        return `${display(decimal(row.weight))} × ${grade.includes('/') ? `(${grade})` : grade}`
                    })
                    const numerator = display(total)
                    step = `(${operands.join(' + ')}) / ${display(weightsTotal)} = ${numerator.includes('/') ? `(${numerator})` : numerator} / ${display(weightsTotal)} ${display(result).includes('/') ? '≈' : '='} ${displayExpansion(result)}`
                }
            }
        }
        if (context !== 'root') {
            groupResults[context] = result ? compare(decimal(displayRounded(result)), result) === 0 ? displayRounded(result) : `≈ ${displayRounded(result)}` : null
            groupSteps[context] = adjustments.length ? '' : step
        } else {
            rootStep = step
        }
        return result
    }
    const roundGrade = (value) => String((value.numerator * 2n + value.denominator) / (value.denominator * 2n))
    const resolvedTotal = resolve('root')
    const total = resolvedTotal === absentOptional ? null : resolvedTotal
    return { parts: Object.fromEntries(parts.map((part) => {
        const id = part.gradingPartId ?? part.id
        const value = partResults[id]
        return [id, value && value !== absentOptional ? display(value) : null]
    })), groups: groupResults, groupSteps, partSteps, total: total ? roundGrade(total) : null, totalStep: total ? `${rootStep || `Rechenwert ${display(total)}`}; kaufmännisch → Note ${roundGrade(total)}` : '' }
}

export function simulationPartExplanation(part, valuesByEntry) {
    const grade = simulationPartGrade(part, valuesByEntry)
    if (grade === null) return ''
    if (part.points_assessment_mode === 'sum_percent') {
        const points = simulationPointsResult(part.entries || [], valuesByEntry)
        const band = standardPercentageGrades.find((item) => String(item.grade) === points.grade)
        return `${band?.min > 0 ? `ab ${String(band.min).replace('.', ',')} %` : 'unter 50 %'} → Note ${points.grade}`
    }
    if (part.points_assessment_mode === 'sign_grade') {
        const balance = simulationSignBalance(part, valuesByEntry)
        return `Saldo ${display(balance)}; ${grade === '5' ? `unter Grenze ${part.sign_grade_thresholds[4]} für Note 4` : `Grenze ${part.sign_grade_thresholds[grade]} erreicht`} → Note ${grade}`
    }
    const entry = part.entries?.[0]
    const values = valuesByEntry[entry?.id] || []
    const configuration = entry?.standard_grade_occurrences
    if (configuration?.mode === 'single') return `Einzelübernahme: ${values[0]} → Note ${grade}`
    if (configuration?.mean?.mode === 'equal') return `(${values.join(' + ')}) / ${values.length} = ${grade}`
    if (configuration?.mean?.mode === 'weighted') return `(${values.map((value, index) => `${value} × ${display(decimal(configuration.mean.weights[index]))}`).join(' + ')}) / 100 = ${grade}`
    return ''
}

export function simulationPartSummary(part, valuesByEntry, grade = simulationPartGrade(part, valuesByEntry)) {
    if (part.points_assessment_mode === 'sum_percent') {
        const points = simulationPointsResult(part.entries || [], valuesByEntry)
        return points ? `${points.achieved} / ${points.maximum} Punkte · ${points.percentage} % · Note ${points.grade}` : '–'
    }
    if (['plus_minus', 'sign_grade', 'sign_adjust'].includes(part.points_assessment_mode)) {
        const balance = simulationSignBalance(part, valuesByEntry)
        const saldo = balance ? `Saldo ${balance.numerator > 0n ? '+' : ''}${display(balance)}` : 'Saldo –'
        return part.points_assessment_mode === 'sign_adjust' ? saldo : `${saldo} · Note ${grade ?? '–'}`
    }
    return grade ?? '–'
}

export function simulationEntryGrade(entry, part, valuesByEntry) {
    const values = valuesByEntry[entry.id] || []
    if (!values.length || !entry.has_properties) return null
    if (entry.properties_mode === 'points' && part?.points_assessment_mode === 'sum_percent') {
        return pointsResult([entry], valuesByEntry)
    }
    if (simulationPropertyMode(entry) !== 'grades' || values.some((value) => !/^[1-5]$/.test(value))) return null
    const configuration = entry.standard_grade_occurrences
    if (configuration?.mode === 'single') return values.length === 1 ? values[0] : null
    if (part?.points_assessment_mode !== 'grade_mean' || configuration?.mode !== 'fixed'
        || !Number.isSafeInteger(configuration.count) || configuration.count < 2 || values.length !== configuration.count) return null
    if (configuration.mean?.mode === 'equal') {
        return display(fraction(values.reduce((total, value) => total + BigInt(value), 0n), BigInt(values.length)))
    }
    if (configuration.mean?.mode !== 'weighted' || configuration.mean.weights?.length !== values.length) return null
    const weights = configuration.mean.weights.map(decimal)
    if (weights.some((weight) => !weight || weight.numerator < 0n || compare(weight, fraction(100n)) > 0)
        || compare(sum(weights), fraction(100n)) !== 0) return null
    const weighted = sum(values.map((value, index) => fraction(BigInt(value) * weights[index].numerator, weights[index].denominator)))
    return display(fraction(weighted.numerator, weighted.denominator * 100n))
}

export function simulationPartGrade(part, valuesByEntry) {
    const entries = part.entries || []
    if (part.points_assessment_mode === 'sum_percent') return pointsResult(entries, valuesByEntry)
    if (entries.length === 1 && simulationPropertyMode(entries[0]) === 'grades') {
        return simulationEntryGrade(entries[0], part, valuesByEntry)
    }
    if (part.points_assessment_mode !== 'sign_grade') return null
    const thresholds = part.sign_grade_thresholds
    if (!thresholds || Object.keys(thresholds).length !== 4
        || [4, 3, 2, 1].some((grade) => !Number.isSafeInteger(thresholds[grade])
            || (grade < 4 && thresholds[grade] <= thresholds[grade + 1]))) return null
    const values = entries.flatMap((entry) => valuesByEntry[entry.id] || [])
    if (!values.length) return null
    let balance = 0n
    for (const raw of values) {
        const value = raw.replaceAll('−', '-')
        if (!/^(?:\+{1,50}|-{1,50}|0|~)$/.test(value)) return null
        balance += value === '~' ? 1n : value === '0' ? 0n : BigInt(value.length) * (value[0] === '+' ? 2n : -2n)
    }
    return String([1, 2, 3, 4].find((grade) => balance >= BigInt(thresholds[grade]) * 2n) ?? 5)
}
