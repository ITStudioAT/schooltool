import { ref } from 'vue'
import { showTest } from '@/actions/App/Http/Controllers/Admin/MultipleChoiceController'

export function useWorkMcAttemptStatus(testIds) {
    const studentAttempts = ref([])
    const studentTests = ref([])
    const studentStatusLoading = ref(false)
    const studentStatusError = ref('')
    let studentStatusSequence = 0

    function clearStudentStatuses() {
        studentStatusSequence++
        studentAttempts.value = []
        studentTests.value = []
        studentStatusLoading.value = false
        studentStatusError.value = ''
    }
    function studentStatus(id, testId = null, { requireActualStart = false } = {}) {
        if (!testIds.value.length) return 'Kein MC-Test'
        if (studentStatusLoading.value) return 'Wird geladen …'
        if (studentStatusError.value) return 'Status nicht verfügbar'
        const matches = studentAttempts.value.filter(attempt => String(attempt.student_id) === String(id)
            && (testId === null || String(attempt.test_id) === String(testId)))
        if (!matches.length) return 'Kein Test zugeordnet'
        const labels = { not_started: requireActualStart ? 'Noch nicht gestartet' : 'Noch nicht begonnen', ready: 'Bereit', in_progress: 'In Bearbeitung', submitted: 'Abgegeben', timeout: 'Zeit abgelaufen' }
        return matches.map(attempt => {
            const testPaused = studentTests.value.some(test => String(test.id) === String(attempt.test_id) && test.paused_at)
            if (!attempt.completed_at && !['submitted', 'timeout'].includes(attempt.status) && (attempt.individual_paused_at || testPaused)) return 'Pausiert'
            const status = requireActualStart && attempt.status === 'in_progress' && !attempt.started_at
                ? (attempt.ready_at ? 'ready' : 'not_started')
                : attempt.status
            return labels[status] || 'Status unbekannt'
        }).join(' · ')
    }
    function studentTestTimedOut(id) {
        return !studentStatusLoading.value && !studentStatusError.value && studentAttempts.value.some(attempt =>
            String(attempt.student_id) === String(id) && attempt.status === 'timeout' && !attempt.individual_paused_at)
    }
    function attemptsForStudent(id) {
        return studentAttempts.value.filter(attempt => String(attempt.student_id) === String(id)
            && studentTests.value.some(test => String(test.id) === String(attempt.test_id) && test.status === 'published'))
    }
    async function loadStudentStatuses() {
        const operation = ++studentStatusSequence
        studentAttempts.value = []
        studentTests.value = []
        studentStatusError.value = ''
        studentStatusLoading.value = testIds.value.length > 0
        if (!studentStatusLoading.value) return
        try {
            const responses = await Promise.all(testIds.value.map(async id => {
                const { data } = await axios.get(showTest.url(id))
                if (String(data?.test?.id) !== String(id) || !Array.isArray(data?.attempts)) throw new Error()
                const serverTime = Date.parse(data.server_now)
                const serverOffset = Number.isFinite(serverTime) ? serverTime - Date.now() : null
                return { ...data, test: { ...data.test, serverOffset } }
            }))
            if (operation === studentStatusSequence) {
                studentAttempts.value = responses.flatMap(({ attempts, test }) => attempts.map(attempt => ({ ...attempt,
                    test_id: test.id, test_title: test.title })))
                studentTests.value = responses.map(({ test }) => test)
            }
        } catch {
            if (operation === studentStatusSequence) studentStatusError.value = 'Der MC-Test-Status konnte nicht geladen werden.'
        } finally {
            if (operation === studentStatusSequence) studentStatusLoading.value = false
        }
    }

    return { studentAttempts, studentTests, studentStatusLoading, studentStatusError, studentStatus, studentTestTimedOut, attemptsForStudent,
        loadStudentStatuses, clearStudentStatuses }
}
