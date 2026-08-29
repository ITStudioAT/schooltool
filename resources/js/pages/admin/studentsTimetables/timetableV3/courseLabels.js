const COURSE_BASE_DISPLAY_ALIASES = Object.freeze({
    GPB: 'GS',
    GSGPB: 'GS',
    GWB: 'GW',
    LET: 'LPT',
    MEMU: 'ME',
    MU: 'ME',
    OEK: 'ÖKO',
    OEKO: 'ÖKO',
    OKO: 'ÖKO',
    OKON: 'ÖKO',
    SPA: 'S',
})

const CANONICAL_MODULE_DISPLAY_NAMES = Object.freeze({
    ET: 'Ethik',
    ETH: 'Ethik',
    LPT: 'Lern- und Präsentationstechniken',
    REV: 'Religion evangelisch',
    RIS: 'Religion Islam',
    RK: 'Religion katholisch',
    ROR: 'Religion orthodox',
})

const LEADING_COURSE_CODE_PATTERN = /^([A-Za-zÄÖÜäöüß]+)(\d*)/u
const TIMETABLE_MODULE_COURSE_COLLATOR = new Intl.Collator('de-AT', {
    numeric: true,
    sensitivity: 'base',
})

function canonicalCourseCodeParts(value) {
    const label = String(value || '').trim()
    const match = label.match(LEADING_COURSE_CODE_PATTERN)
    if (!match) return null

    const [, sourceBase, moduleNumber] = match
    const normalizedBase = sourceBase.toLocaleUpperCase('de-AT')

    return {
        code: `${COURSE_BASE_DISPLAY_ALIASES[normalizedBase] || sourceBase}${moduleNumber || ''}`,
        moduleNumber: moduleNumber || '',
        sourceLength: match[0].length,
    }
}

export function canonicalTimetableCourseLabel(value, moduleCode = '') {
    const label = String(value || '').trim()
    if (!label) return ''

    const labelParts = canonicalCourseCodeParts(label)
    if (!labelParts) return label

    const normalizedLabel = `${labelParts.code}${label.slice(labelParts.sourceLength)}`
    const moduleParts = canonicalCourseCodeParts(moduleCode)
    if (!moduleParts) return normalizedLabel

    const normalizedModuleCode = moduleParts.code
    const codesMatch = labelParts.code.toLocaleUpperCase('de-AT')
        === normalizedModuleCode.toLocaleUpperCase('de-AT')
    const moduleNumbersMatch = labelParts.moduleNumber !== ''
        && labelParts.moduleNumber === moduleParts.moduleNumber

    if (!codesMatch && !moduleNumbersMatch) return normalizedLabel

    return `${normalizedModuleCode}${label.slice(labelParts.sourceLength)}`
}

export function canonicalTimetableModuleName(value, moduleCode = '') {
    const canonicalModuleCode = canonicalTimetableCourseLabel(moduleCode)
    const moduleParts = canonicalCourseCodeParts(canonicalModuleCode)
    const moduleBase = String(moduleParts?.code || '').replace(/\d+$/u, '').toLocaleUpperCase('de-AT')

    if (CANONICAL_MODULE_DISPLAY_NAMES[moduleBase]) {
        const moduleNumber = moduleParts?.moduleNumber || ''

        return `${CANONICAL_MODULE_DISPLAY_NAMES[moduleBase]}${moduleNumber ? ` ${moduleNumber}` : ''}`
    }

    return String(value || '').trim()
}

export function sortTimetableModuleCourses(courses, moduleCode = '') {
    const courseList = Array.isArray(courses) ? courses : []

    return [...courseList].sort((firstCourse, secondCourse) => {
        const firstSortValues = [
            canonicalTimetableCourseLabel(firstCourse?.title, moduleCode),
            canonicalTimetableCourseLabel(firstCourse?.course_title, moduleCode),
            String(firstCourse?.key || '').trim(),
        ]
        const secondSortValues = [
            canonicalTimetableCourseLabel(secondCourse?.title, moduleCode),
            canonicalTimetableCourseLabel(secondCourse?.course_title, moduleCode),
            String(secondCourse?.key || '').trim(),
        ]

        for (let index = 0; index < firstSortValues.length; index += 1) {
            const comparison = TIMETABLE_MODULE_COURSE_COLLATOR.compare(
                firstSortValues[index],
                secondSortValues[index],
            )

            if (comparison !== 0) return comparison
        }

        return 0
    })
}
