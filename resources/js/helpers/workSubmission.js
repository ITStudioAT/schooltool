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
