import { workManualCompletionFields } from '@/helpers/workProgress'

export function isInitialWork(work) {
    const hasValue = value => value !== null && value !== undefined && value !== ''
    const preparationDeleted = Boolean(work?.status?.student_distribution?.deleted_at)
    const hasStatus = Object.entries(work?.status || {}).some(([key, value]) =>
        !['automation_enabled', 'multiple_choice_tests', 'creation_receipt', 'final_deadline_saved', 'final_deadline_sync_pending',
            'provision_method', 'submission_method', 'grade_visibility'].includes(key)
        && !(preparationDeleted && ['student_distribution', 'student_task_files'].includes(key))
        && (!Object.values(workManualCompletionFields).includes(key) || value === true)
        && (Array.isArray(value) ? value.length > 0 : hasValue(value)))
    const hasAssessment = (work?.groups || []).some(group =>
        hasValue(group.grade) || hasValue(group.comment)
        || ['grades', 'points', 'comments'].some(key => {
            const values = group[key]
            if (typeof values !== 'object') return hasValue(values)
            return Object.values(values || {}).some(item =>
                typeof item === 'object' && item !== null
                    ? hasValue(item.grade ?? item.points ?? item.comment)
                    : hasValue(item))
        }))

    return !hasStatus && !hasAssessment
}

export function workConnectionLabel(folderState, failed = false) {
    if (folderState) return 'Noch kein Ordner verbunden.'
    return failed ? 'Ordnerverbindung konnte nicht geprüft werden.' : 'Ordnerverbindung wird geprüft …'
}

export function workLinkedMcTest(work, date = '') {
    const slot = date || work?.date_for_all_groups || 'all'
    const binding = work?.status?.multiple_choice_tests?.[slot]
    if (!binding?.test_id || String(binding.work_id) !== String(work?.id)) return null
    const details = work.multiple_choice_test_details?.[String(binding.test_id)]
    const title = work?.multiple_choice_test_titles?.[String(binding.test_id)]
    return { ...details, title: typeof title === 'string' && title.trim() ? title : `Nr. ${binding.test_id}` }
}

export function workLinkedMcTestTitle(work, date = '') {
    return workLinkedMcTest(work, date)?.title ?? null
}

export function workNeedsPreparation(work, folderState) {
    const status = { ...work?.status }
    delete status.communicated_deadline_at
    delete status.communicated_deadline_date
    return isInitialWork({ ...work, status }) && folderState?.has_import !== true
}

export function confirmedExerciseFolder(checked) {
    if (checked?.status === 'found') return checked.folder
    const exercise = checked?.data?.exercise
    return exercise?.reusable === 'linked' ? exercise.reusable_folder : null
}

export function workFolderNeedsRetry(state, checked) {
    const exercise = checked?.data?.exercise ?? state?.exercise
    return exercise?.status !== 'found' && exercise?.reusable !== 'linked'
        && (state?.link_preparation?.status === 'partial' || exercise?.status === 'missing')
}

export function workPreparationLabel(checked) {
    if (checked.status === 'found') {
        if (checked.data?.exercise?.preparation?.status === 'ready') return 'Aufgaben bereitstellen'
        if (checked.data?.exercise?.email_dispatch?.status === 'sent') return 'Abgaben einsammeln'
        if (checked.data?.exercise?.assignment_status === 'missing') return 'Aufgabenstellung fehlt'
        const preparation = checked.data?.exercise?.email_preparation
        return preparation?.status === 'ready' ? 'Versandbereit'
            : preparation?.status === 'unknown' ? 'Vorbereitung prüfen' : 'E-Mails vorbereiten'
    }
    return checked.status === 'missing' ? 'LF erstellen' : 'LF nicht bestätigt'
}
