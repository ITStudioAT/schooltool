import { parseLocalDate } from '@/helpers/date'

function normalizeGradeKey(gradeKey) {
    return String(gradeKey || '').trim().toUpperCase()
}

function isNaGradeKey(gradeKey) {
    return normalizeGradeKey(gradeKey) === 'NA'
}

function isNbGradeKey(gradeKey) {
    return normalizeGradeKey(gradeKey) === 'NB'
}

function isNbValue(value) {
    if (value == null || value === '') return false
    return isNbGradeKey(value)
}

function normalizeDateKey(date) {
    if (!date) return ''
    if (typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) return date.slice(0, 10)
    const parsed = parseLocalDate(date)
    if (Number.isNaN(parsed.getTime())) return ''
    const y = parsed.getFullYear()
    const m = String(parsed.getMonth() + 1).padStart(2, '0')
    const d = String(parsed.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
}

function defaultGradeForWork(work) {
    if (!work) return ''
    const dg = String(work.default_grade || '').trim()
    if (!dg) return ''
    const exists = (work.grades || []).some((g) => normalizeGradeKey(g?.grade) === normalizeGradeKey(dg))
    return exists ? dg : ''
}

function effectiveGradeKeyForEntry(entry, workOverride, teachingWorks) {
    const fromApi = String(entry?.effective_grade || '').trim()
    if (fromApi) return fromApi
    const direct = String(entry?.grade || '').trim()
    if (direct) return direct
    const work = workOverride || workConfigForType(entry?.type, teachingWorks)
    return defaultGradeForWork(work)
}

function isGradedEntry(entry, workOverride, teachingWorks) {
    return effectiveGradeKeyForEntry(entry, workOverride, teachingWorks) !== ''
}

function workConfigForType(type, teachingWorks) {
    if (!type) return null
    return (teachingWorks || []).find((w) => w.short_name === type) || null
}

function gradeValueForWork(work, gradeKey) {
    if (!work || !gradeKey) return null

    const lookup = normalizeGradeKey(gradeKey)
    const grade = (work.grades || []).find((g) => normalizeGradeKey(g.grade) === lookup)
    if (!grade || grade.value == null) {
        return numericValueFromGradeKey(gradeKey)
    }

    const num = parseFloat(String(grade.value).replace(',', '.'))
    return Number.isNaN(num) ? null : num
}

function numericValueFromGradeKey(gradeKey) {
    const normalized = String(gradeKey || '').trim().replace(',', '.')
    if (!/^[+-]?\d+(?:\.\d+)?$/.test(normalized)) return null

    const value = parseFloat(normalized)
    return Number.isNaN(value) ? null : value
}

function pointsGradeForWork(work, points) {
    if (!work || work.calculation !== 'points') return null
    const table = (work.semester_points_table || []).length ? work.semester_points_table : (work.points_table || [])
    const fallback = (work.semester_points_sonst_grade || work.points_sonst_grade || '').toString().trim()
    if (!table.length) return fallback || null
    const sorted = [...table].sort((a, b) => (b.min_points ?? 0) - (a.min_points ?? 0))
    const found = sorted.find((row) => points >= (row.min_points ?? 0))
    return found?.grade || fallback || null
}

function numericGradeValuesForWorkEntries(workEntries, work, teachingWorks) {
    const gradeKeys = (workEntries || [])
        .map((entry) => effectiveGradeKeyForEntry(entry, work, teachingWorks))
        .filter((gradeKey) => String(gradeKey || '').trim() !== '')

    if (!gradeKeys.length) return []

    const numericValues = gradeKeys
        .map((gradeKey) => numericValueFromGradeKey(gradeKey))
        .filter((value) => value !== null)

    return numericValues.length === gradeKeys.length ? numericValues : []
}

function entriesForSemester(entries, semester, semesterCount, sem2Boundary) {
    if (semesterCount !== 2) return entries
    if (!sem2Boundary) return entries
    return (entries || []).filter((entry) => {
        if (!entry.date) return true
        const d = normalizeDateKey(entry.date)
        if (!d) return true
        if (semester === 1) return d < sem2Boundary
        if (semester === 2) return d >= sem2Boundary
        return true
    })
}

export function buildCategoryGroups(entries, teachingWorks, grading) {
    const categories = grading?.categories || []
    const worksByType = new Map((teachingWorks || []).map((w) => [w.short_name, w]))

    const entriesByType = new Map()
    ;(entries || []).forEach((entry) => {
        const type = entry?.type
        if (!type) return
        if (!entriesByType.has(type)) entriesByType.set(type, [])
        entriesByType.get(type).push(entry)
    })

    return categories.map((cat) => {
        const works = (cat.works || []).map((w) => (typeof w === 'string' ? { short_name: w, factor: 100 } : w))
        const workAverages = []
        const categoryRequireAll = Boolean(cat?.require_all_entries)
        let hasAny = false
        let hasUngraded = false
        let hasNa = false

        works.forEach((workItem) => {
            const type = workItem.short_name
            const work = worksByType.get(type)
            if (!work) return
            const workEntries = entriesByType.get(type) || []
            if (workEntries.length) hasAny = true
            if (workEntries.some((e) => !isGradedEntry(e, null, teachingWorks))) hasUngraded = true
            if (categoryRequireAll && workEntries.some((e) => isNaGradeKey(effectiveGradeKeyForEntry(e, work, teachingWorks)))) hasNa = true

            const factorPercent = parseFloat(workItem.factor)
            const weight = (Number.isNaN(factorPercent) ? 0 : factorPercent) / 100

            if (work.calculation === 'points') {
                const numericGradeValues = numericGradeValuesForWorkEntries(workEntries, work, teachingWorks)
                if (numericGradeValues.length) {
                    const avg = numericGradeValues.reduce((s, v) => s + v, 0) / numericGradeValues.length
                    workAverages.push({ value: avg, weight })
                    return
                }
                const values = workEntries
                    .map((e) => gradeValueForWork(work, effectiveGradeKeyForEntry(e, work, teachingWorks)))
                    .filter((v) => v !== null)
                if (!values.length) {
                    if (workEntries.length) return

                    const grade = pointsGradeForWork(work, 0)
                    let numericGrade = gradeValueForWork(work, grade)
                    if (numericGrade === null && grade != null && grade !== '') {
                        const parsed = parseFloat(String(grade).replace(',', '.'))
                        numericGrade = Number.isNaN(parsed) ? null : parsed
                    }
                    if (numericGrade !== null) workAverages.push({ value: numericGrade, weight })
                    return
                }
                const sum = values.reduce((s, v) => s + v, 0)
                const rounded = Number.isInteger(sum) ? sum : Number(sum.toFixed(2))
                const grade = pointsGradeForWork(work, rounded)
                let numericGrade = gradeValueForWork(work, grade)
                if (numericGrade === null && grade != null && grade !== '') {
                    const parsed = parseFloat(String(grade).replace(',', '.'))
                    numericGrade = Number.isNaN(parsed) ? null : parsed
                }
                if (numericGrade !== null) workAverages.push({ value: numericGrade, weight })
                return
            }

            const values = workEntries
                .map((e) => gradeValueForWork(work, effectiveGradeKeyForEntry(e, work, teachingWorks)))
                .filter((v) => v !== null)
            if (values.length) {
                const avg = values.reduce((s, v) => s + v, 0) / values.length
                workAverages.push({ value: avg, weight })
            }
        })

        const isNb = categoryRequireAll && hasAny && hasUngraded
        const isNa = categoryRequireAll && hasNa
        let value = null
        if (isNa) {
            value = 'NA'
        } else if (isNb) {
            value = 'NB'
        } else if (workAverages.length) {
            const totalW = workAverages.reduce((s, w) => s + w.weight, 0) || 1
            value = Number((workAverages.reduce((s, w) => s + w.value * w.weight, 0) / totalW).toFixed(2))
        }

        return {
            name: cat.name || 'Kategorie',
            weight: cat.weight ?? 0,
            value,
            isNb,
            isNa,
        }
    })
}

export function totalFromCategoryGroups(groups) {
    if (!groups?.length) return null
    if (groups.some((cat) => cat?.isNa)) return 'NA'
    if (groups.some((cat) => cat?.isNb)) return 'NB'
    const weighted = groups
        .map((cat) => ({
            value: cat.value != null ? cat.value : cat.grade != null ? parseFloat(String(cat.grade).replace(',', '.')) : null,
            weight: (parseFloat(cat.weight) || 0) / 100,
        }))
        .filter((c) => c.value != null && !Number.isNaN(c.value) && c.weight > 0)
    if (!weighted.length) return null
    const totalW = weighted.reduce((s, c) => s + c.weight, 0) || 1
    return Number((weighted.reduce((s, c) => s + c.value * c.weight, 0) / totalW).toFixed(2))
}

function parseStoredGrade(raw) {
    if (raw == null || raw === '') return null
    const key = normalizeGradeKey(raw)
    if (isNbGradeKey(key)) return 'NB'
    if (isNaGradeKey(key)) return 'NA'
    const parsed = parseFloat(String(raw).replace(',', '.'))
    return Number.isNaN(parsed) ? null : parsed
}

function computeYearlyGrade(sem1Calculated, sem2Calculated, student, grading) {
    const sem1Weight = parseFloat(grading.semester_1_weight)
    const sem2Weight = parseFloat(grading.semester_2_weight)
    const w1 = Number.isNaN(sem1Weight) ? 50 : sem1Weight
    const w2 = Number.isNaN(sem2Weight) ? 50 : sem2Weight
    const totalWeight = w1 + w2
    if (totalWeight <= 0) return null

    let sem1Value = sem1Calculated
    if (grading.use_semester_grade_only) {
        const raw = student?.sem_1_grade
        const key = normalizeGradeKey(raw)
        if (isNbGradeKey(key)) {
            sem1Value = 'NB'
        } else {
            const parsed = parseFloat(String(raw || '').replace(',', '.'))
            sem1Value = Number.isNaN(parsed) ? null : parsed
        }
    }
    const sem2Value = sem2Calculated

    if (isNaGradeKey(sem1Value) || isNaGradeKey(sem2Value)) return 'NA'
    if (isNbValue(sem1Value) || isNbValue(sem2Value)) return 'NB'
    if (sem1Value == null || sem2Value == null) return null

    return Number((((sem1Value * w1) + (sem2Value * w2)) / totalWeight).toFixed(2))
}

/**
 * Computes effective grades for a single student across semesters.
 *
 * @param {Object} student - Student object (with sem_1_grade, sem_2_grade, etc.)
 * @param {Array} studentEntries - All entries for this student
 * @param {Array} teachingWorks - Work type definitions from schema
 * @param {Object} grading - Grading config from schema
 * @param {number} semesterCount - 1 or 2
 * @param {string|null} sem2StartDate - Boundary date for semester 2
 * @returns {{ sem1: number|string|null, sem2: number|string|null, year: number|string|null }}
 */
export function computeStudentGrades(student, studentEntries, teachingWorks, grading, semesterCount, sem2StartDate) {
    const sem2Boundary = normalizeDateKey(sem2StartDate)
    const result = { sem1: null, sem2: null, year: null }

    if (semesterCount === 2) {
        const sem1Entries = entriesForSemester(studentEntries, 1, 2, sem2Boundary)
        const sem2Entries = entriesForSemester(studentEntries, 2, 2, sem2Boundary)

        const sem1Groups = buildCategoryGroups(sem1Entries, teachingWorks, grading)
        const sem1Calculated = totalFromCategoryGroups(sem1Groups)

        const sem2Groups = buildCategoryGroups(sem2Entries, teachingWorks, grading)
        const sem2Calculated = totalFromCategoryGroups(sem2Groups)

        const useSemGradeOnly = !!grading.use_semester_grade_only
        const storedSem1Parsed = student.sem_1_grade != null ? parseStoredGrade(student.sem_1_grade) : null

        result.sem1 = useSemGradeOnly ? storedSem1Parsed : sem1Calculated
        result.sem2 = sem2Calculated
        result.year = computeYearlyGrade(sem1Calculated, sem2Calculated, student, grading)
    } else {
        const groups = buildCategoryGroups(studentEntries, teachingWorks, grading)
        result.sem1 = totalFromCategoryGroups(groups)
    }

    return result
}

export function formatGrade(value) {
    if (value == null || value === '') return '–'
    if (isNbValue(value)) return 'NB'
    if (isNaGradeKey(value)) return 'NA'
    const num = typeof value === 'number' ? value : parseFloat(String(value).replace(',', '.'))
    if (!Number.isNaN(num)) return num.toFixed(2)
    return String(value)
}

export function gradeClass(value) {
    if (isNaGradeKey(value)) return 'grade--na'
    if (isNbValue(value)) return 'grade--nb'
    if (value != null && value !== '') return 'grade--ok'
    return 'grade--empty'
}
