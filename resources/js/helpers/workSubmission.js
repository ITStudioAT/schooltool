import { assessmentTimeText } from '@/helpers/workAssessmentTimes'
import { workDeadlineExpired } from '@/helpers/date'
import { workProvisionRecords } from '@/helpers/workDispatch'

export function personalWorkProvision(work, studentId) {
    if (work?.is_group_work && !(work.groups || []).some(group =>
        (group.student_ids || []).some(id => String(id) === String(studentId)))) return null
    return workProvisionRecords(work, studentId).filter(event => event.participant_id)
        .sort((first, second) => Date.parse(first.available_at) - Date.parse(second.available_at))[0] || null
}

export function personalWorkAssessment(work, studentId) {
    if (!work?.id || !studentId || (work.is_group_work && !(work.groups || []).some(group =>
        (group.student_ids || []).some(id => String(id) === String(studentId))))) return null
    const receipt = (work.status?.assessment_json_imports || [])
        .findLast(item => String(item.student_id) === String(studentId))
    const record = receipt?.record
    return record && receipt.exercise_id && receipt.participant_id
        && record.participant_id === receipt.participant_id
        && /^[a-f0-9]{64}$/.test(receipt.record_checksum || '')
        && record.record_checksum === receipt.record_checksum ? record : null
}

function matchesDeadline(work, deadline) {
    if (!work?.finish_until_date || !work?.finish_until_time || !Number.isFinite(Date.parse(deadline))) return false
    const parts = new Intl.DateTimeFormat('sv-SE', {
        timeZone: 'Europe/Vienna', year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
    }).formatToParts(new Date(deadline))
    const values = Object.fromEntries(parts.map(part => [part.type, part.value]))
    return `${values.year}-${values.month}-${values.day}` === work.finish_until_date
        && `${values.hour}:${values.minute}` === work.finish_until_time && values.second === '00'
}

export function workCollectionEvidence(work, studentIds) {
    if (!studentIds.length) return null
    const receipts = studentIds.map(studentId => {
        const receipt = (work.status?.assessment_json_imports || []).findLast(item => String(item.student_id) === String(studentId))
        return personalWorkAssessment(work, studentId) ? receipt : null
    })
    if (receipts.some(receipt => !receipt)) return null
    const finalDownloads = receipts.every(receipt => {
        const saved = (work.status?.assessment_json_packages || []).findLast(item => item.exercise_id === receipt.exercise_id)
        const download = saved?.final_download
        return download?.exercise_id === receipt.exercise_id && /^[a-f0-9]{64}$/.test(download.download_checksum || '')
            && Number.isFinite(Date.parse(saved.imported_at)) && Number.isFinite(Date.parse(receipt.imported_at))
            && Date.parse(saved.imported_at) >= Date.parse(receipt.imported_at)
            && matchesDeadline(work, download.deadline_at)
            && Date.parse(download.download_completed_at) > Date.parse(download.deadline_at)
            && download.participants?.some(person => person.participant_id === receipt.participant_id && Array.isArray(person.submission_sha256))
    })
    if (finalDownloads) return 'local'
    const status = work.submission_check_status
    const check = (work.status?.submission_checks || []).at(-1)?.check
    if (status?.complete !== true || status.deadline_date !== work.finish_until_date
        || status.deadline_time !== work.finish_until_time || check?.state !== 'complete'
        || check.checked_at !== status.checked_at || !Number.isFinite(Date.parse(check.checked_at))) return null
    return receipts.every(receipt => receipt.exercise_id === check.exercise_id
        && status.student_ids?.some(id => String(id) === String(receipt.student_id))
        && check.participants?.some(person => person.participant_id === receipt.participant_id)) ? 'checked' : null
}

export function workSubmissionStatus(work, studentId, now = Date.now()) {
    const unknown = { state: 'unknown', color: 'orange-darken-2',
        label: 'Abgabestatus ungeklärt: kein bestätigter personenbezogener Abgabenachweis.' }
    if (!work?.id || !studentId) return unknown
    if (work.is_group_work && !(work.groups || []).some(group =>
        (group.student_ids || []).some(id => String(id) === String(studentId)))) return unknown
    const receipt = (work.status?.assessment_json_imports || [])
        .findLast(item => String(item.student_id) === String(studentId))
    const record = personalWorkAssessment(work, studentId)
    if (!record) return unknown

    const packageReceipt = (work.status?.assessment_json_packages || [])
        .findLast(item => item.exercise_id === receipt.exercise_id)
    const currentPackage = Number.isFinite(Date.parse(packageReceipt?.imported_at))
        && Number.isFinite(Date.parse(receipt.imported_at))
        && Date.parse(packageReceipt.imported_at) >= Date.parse(receipt.imported_at)
    const download = packageReceipt?.final_download
    const person = download?.participants?.find(item => item.participant_id === receipt.participant_id)

    if (record.submission_state === 'received') {
        const collected = Number.isFinite(Date.parse(record.email_collected_at))
            ? ` Abgabe eingesammelt: ${assessmentTimeText(record.email_collected_at)}.` : ''
        const saved = currentPackage && download?.exercise_id === receipt.exercise_id && download.download_checksum
            && Number.isFinite(Date.parse(download.download_completed_at)) && person?.submission_sha256?.length > 0
            ? ` Gesicherter lokaler Abschlussbestand: ${assessmentTimeText(download.download_completed_at)}.` : ''
        const late = /verspätet|nach (?:der )?frist/i.test(record.submission_note || '')
            ? ` Hinweis aus dem Abgabenachweis: ${record.submission_note}` : ''
        return { state: 'received', color: 'success', label: `Abgabe liegt vor (importierter personenbezogener Nachweis).${collected}${saved}${late}` }
    }
    if (record.submission_state !== 'not_received' || !workDeadlineExpired(work, now)) return unknown
    if (currentPackage && download?.exercise_id === receipt.exercise_id && download.download_checksum
        && matchesDeadline(work, download.deadline_at) && Date.parse(download.download_completed_at) > Date.parse(download.deadline_at)
        && Array.isArray(person?.submission_sha256) && person.submission_sha256.length === 0) {
        const scope = String(download.scope || '').trim().replace(/[.]+$/, '')
        return { state: 'not_received', color: 'error',
            label: `Keine Abgabe im bestätigten lokalen Abschlussdownload vom ${assessmentTimeText(download.download_completed_at)}. Bestand: ${scope}. Keine Aussage über eine vollständige Serversuche.` }
    }

    const status = work.submission_check_status
    const check = (work.status?.submission_checks || []).at(-1)?.check
    const checkedPerson = check?.participants?.find(item => item.participant_id === receipt.participant_id)
    if (status?.complete === true && status.deadline_date === work.finish_until_date
        && status.deadline_time === work.finish_until_time && (status.student_ids || []).some(id => String(id) === String(studentId))
        && check?.state === 'complete' && check.exercise_id === receipt.exercise_id && check.checked_at === status.checked_at
        && checkedPerson?.email_result === 'not_found' && checkedPerson.other_result === 'not_received') {
        return { state: 'not_received', color: 'error',
            label: `Keine Abgabe laut abgeschlossener Abgabeprüfung vom ${assessmentTimeText(check.checked_at)} (E-Mail und weitere Abgabewege).` }
    }

    const decision = packageReceipt?.teacher_absence_decision
    if (currentPackage && decision?.exercise_id === receipt.exercise_id && decision.decision_checksum
        && matchesDeadline(work, decision.deadline_at) && decision.participant_ids?.includes(receipt.participant_id)) {
        return { state: 'not_received', color: 'error',
            label: `Nichtabgabe laut ausdrücklicher Lehrkraftentscheidung vom ${assessmentTimeText(decision.decided_at)}. Keine Aussage über eine vollständige Serversuche.` }
    }
    return unknown
}

export function workCollectionStudentStatus(work, studentId) {
    const existing = workSubmissionStatus(work, studentId)
    const local = work?.collection_run?.local_email_check
    const groupIds = [...new Set((work?.groups || []).flatMap(group => group.student_ids || []).map(String))].sort()
    const released = [...new Set((work?.status?.student_distribution?.student_ids || []).map(String))].sort()
    const checked = (local?.student_ids || []).map(String).sort()
    if (existing.state !== 'received' && local?.server_complete === false && String(work.collection_run?.work_id) === String(work.id)
        && String(work.collection_run?.course_id) === String(work.teaching_course_id)
        && groupIds.length > 0 && JSON.stringify(groupIds) === JSON.stringify(released)
        && JSON.stringify(groupIds) === JSON.stringify(checked) && checked.includes(String(studentId))
        && Number.isFinite(Date.parse(local.checked_at)) && Date.parse(local.checked_at) <= Date.parse(work.collection_run.completed_at)
        && Date.parse(local.checked_at) >= Date.parse(work.status.student_distribution.published_at)
        && !work.status.student_distribution.withdrawn_at) {
        return { state: 'scoped_absence', label: `Bis ${assessmentTimeText(local.checked_at)} keine E-Mail im geprüften lokalen Bestand gefunden. Die Vollständigkeit der Serversuche ist nicht bestätigt.` }
    }
    const receipt = (work?.status?.assessment_json_imports || []).findLast(item => String(item.student_id) === String(studentId))
    const record = personalWorkAssessment(work, studentId)
    const check = (work?.status?.submission_checks || []).at(-1)?.check
    const status = work?.submission_check_status
    const person = check?.participants?.find(item => item.participant_id === receipt?.participant_id)
    const coverage = check?.coverage || []
    const mailboxes = ['guenther.kron@bildung.gv.at', 'guenther.kron@cdgym.at']
    const completeCoverage = coverage.length === 2 && mailboxes.every(mailbox => coverage.some(item =>
        item.mailbox?.toLowerCase() === mailbox && item.scope === 'server_all_folders' && item.verified === true
        && Array.isArray(item.gaps) && item.gaps.length === 0
        && Date.parse(item.start_at) <= Date.parse(person?.available_at || person?.dispatched_at)
        && Date.parse(item.end_at) >= Date.parse(check.checked_at)))
    const otherGapsOnly = Array.isArray(check?.gaps) && check.gaps.every(gap => gap === 'deadline_not_passed'
        || (gap.startsWith('person_unresolved:') && gap !== `person_unresolved:${receipt?.participant_id}`))
    const negative = record && check && ['open', 'complete'].includes(check.state) && status?.checked_at === check.checked_at
        && Number.isFinite(Date.parse(check.checked_at)) && Date.parse(check.checked_at) <= Date.now()
        && status.deadline_date === work.finish_until_date && status.deadline_time === work.finish_until_time
        && status.student_ids?.some(id => String(id) === String(studentId)) && check.exercise_id === receipt.exercise_id
        && person?.email_result === 'not_found' && person.other_result === 'not_received'
        && Array.isArray(person.gaps) && person.gaps.length === 0
        && Array.isArray(check.unresolved_candidates) && check.unresolved_candidates.length === 0 && completeCoverage && otherGapsOnly
    if (negative && existing.state === 'received') {
        return { state: 'unknown', label: 'Widersprüchliche Abgabenachweise: vorhandene Abgabe und negativer Prüfstand. Bitte prüfen.' }
    }
    if (negative) return { state: 'not_received', label: `Bis zur Prüfung am ${assessmentTimeText(check.checked_at)} keine Abgabe festgestellt.` }
    if (existing.state === 'not_received') {
        return { state: 'scoped_absence', label: existing.label }
    }
    if (existing.state === 'unknown' && check) {
        return { state: 'unknown', label: 'Abgabestatus ungeklärt: Der gespeicherte Prüfstand belegt keine vollständige personenbezogene Prüfung.' }
    }
    return existing
}
