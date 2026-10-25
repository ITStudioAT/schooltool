export function workSimulationCollection(work) {
    const run = work?.simulation_collection
    if (run?.origin !== 'mailpit_simulation' || run.simulation !== true || !run.run_id
        || String(run.work_id) !== String(work.id) || String(run.course_id) !== String(work.teaching_course_id)) return null
    const ids = [...new Set((work.groups || []).flatMap(group => group.student_ids || []).map(String))].sort()
    const simulatedIds = Array.isArray(run.student_ids) ? run.student_ids.map(String).sort() : []
    if (ids.length === 0 || JSON.stringify(ids) !== JSON.stringify(simulatedIds)
        || ![run.collected_count, run.non_submission_count, run.processed_count].every(value => Number.isInteger(value) && value >= 0)
        || run.processed_count !== run.collected_count + run.non_submission_count || run.processed_count > ids.length
        || typeof run.complete !== 'boolean' || run.complete !== (run.collected_count > 0 && run.processed_count === ids.length)
        || (run.complete && !Number.isFinite(Date.parse(run.completed_at)))) return null
    return run
}

export function workSimulationEvaluation(work) {
    const simulation = workSimulationCollection(work)
    const evaluation = simulation?.evaluation
    const ids = simulation?.student_ids.map(String).sort() || []
    if (evaluation?.simulation !== true || evaluation.run_id !== simulation.run_id || !Array.isArray(evaluation.rows)
        || JSON.stringify(evaluation.rows.map(row => String(row.student_id)).sort()) !== JSON.stringify(ids)
        || !evaluation.rows.every(row => ['complete', 'partial', 'open'].includes(row.state) && typeof row.result === 'string')
        || evaluation.completed_count !== evaluation.rows.filter(row => row.state === 'complete').length
        || evaluation.partial_count !== evaluation.rows.filter(row => row.state === 'partial').length
        || evaluation.open_count !== evaluation.rows.filter(row => row.state === 'open').length) return null
    return evaluation
}

export function workSimulationNotifications(work) {
    const notification = work?.simulation_notification
    const ids = [...new Set((work?.groups || []).flatMap(group => group.student_ids || []).map(String))]
    if (notification?.origin !== 'mailpit_result_notification' || notification.simulation !== true
        || String(notification.work_id) !== String(work.id) || String(notification.course_id) !== String(work.teaching_course_id)
        || !Array.isArray(notification.rows) || new Set(notification.rows.map(row => String(row.student_id))).size !== notification.rows.length) return null
    const evaluation = workSimulationEvaluation(work)
    const rows = notification.rows.filter(row => {
        const receipt = (work.status?.assessment_json_imports || []).findLast(item => String(item.student_id) === String(row.student_id))
        const current = evaluation?.rows.find(item => String(item.student_id) === String(row.student_id))
        return ids.includes(String(row.student_id)) && Number.isFinite(Date.parse(row.sent_at))
            && /^[a-f0-9]{64}$/.test(row.record_checksum || '') && /^[a-f0-9]{64}$/.test(row.pdf_sha256 || '')
            && receipt?.record_checksum === row.record_checksum && receipt.record?.pdf?.sha256 === row.pdf_sha256
            && (!evaluation || (current?.state === 'complete' && current.pdf_sha256 === row.pdf_sha256))
    })
    return { ...notification, rows }
}
