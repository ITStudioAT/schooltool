import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import TestsV3 from '@/pages/admin/studentsTimetables/testsV3/TestsV3.vue'

describe('Tests V3 summary PDF', () => {
    it('does not render the selected-student table while tests are running', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/testsV3/TestsV3.vue',
            'utf8',
        )
        const tableClassIndex = componentSource.indexOf(
            'class="tests-v3-students__table tests-v3-selection__table"',
        )
        const tableStartIndex = componentSource.lastIndexOf('<v-table', tableClassIndex)
        const tableOpeningTag = componentSource.slice(
            tableStartIndex,
            componentSource.indexOf('>', tableStartIndex),
        )

        expect(tableOpeningTag).toContain('v-else-if="!studentV3TestsRunning"')
    })

    it('hides completed progress when the summary dialog closes', () => {
        const computed = (TestsV3 as any).computed

        expect(computed.showStudentV3TestProgress.call({
            studentV3TestsRunning: true,
            studentV3TestSummaryDialog: false,
            completedStudentV3TestCount: 0,
        })).toBe(true)
        expect(computed.showStudentV3TestProgress.call({
            studentV3TestsRunning: false,
            studentV3TestSummaryDialog: true,
            completedStudentV3TestCount: 10,
        })).toBe(true)
        expect(computed.showStudentV3TestProgress.call({
            studentV3TestsRunning: false,
            studentV3TestSummaryDialog: false,
            completedStudentV3TestCount: 10,
        })).toBe(false)
    })

    it('shows a loading-aware PDF action in the persistent summary dialog', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/testsV3/TestsV3.vue',
            'utf8',
        )
        const dialogTestIdIndex = componentSource.indexOf('data-testid="student-v3-test-summary-dialog"')
        const dialogStartIndex = componentSource.lastIndexOf('<v-dialog', dialogTestIdIndex)
        const dialogEndIndex = componentSource.indexOf('</v-dialog>', dialogStartIndex)
        const dialogSource = componentSource.slice(dialogStartIndex, dialogEndIndex)

        expect(dialogSource).toContain('persistent')
        expect(dialogSource).toContain('prepend-icon="mdi-file-pdf-box"')
        expect(dialogSource).toContain(':loading="studentV3TestSummaryPdfExporting"')
        expect(dialogSource).toContain(':disabled="studentV3TestSummaryPdfExporting || !completedStudentV3TestCount"')
        expect(dialogSource).toContain('@click="downloadStudentV3TestSummaryPdf"')
        expect(dialogSource).toContain('PDF erstellen')
        expect(dialogSource).toContain('Schließen')
    })

    it('builds and downloads the complete dialog summary as a PDF', async () => {
        const methods = (TestsV3 as any).methods
        const selectedStudents = [
            { student_code: '1001', class: '1A', last_name: 'Auer', first_name: 'Anna' },
            { student_code: '2002', class: '1B', last_name: 'Bauer', first_name: 'Berta' },
            { student_code: '3003', class: '1C', last_name: 'Celik', first_name: 'Cem' },
        ]
        const expectedPayload = {
            results: [
                {
                    status: 'passed',
                    class_label: '1A',
                    student_name: 'Auer Anna',
                    message: '',
                },
                {
                    status: 'failed',
                    class_label: '1B',
                    student_name: 'Bauer Berta',
                    message: 'Abweichungen: Aktuelle',
                },
                {
                    status: 'invalid_data',
                    class_label: '1C',
                    student_name: 'Celik Cem',
                    message: 'Semester fehlt.',
                },
            ],
        }
        const pdfBlob = new Blob(['pdf'], { type: 'application/pdf' })
        const post = vi.fn().mockResolvedValue({
            data: pdfBlob,
            headers: {
                'content-disposition': 'attachment; filename="tests-v3.pdf"',
            },
        })
        const downloadBlob = vi.fn()
        const context: any = {
            ...methods,
            selectedStudents,
            completedStudentV3TestCount: 3,
            studentV3TestSummaryPdfExporting: false,
            failedStudentV3TestSummaries: [
                {
                    key: '2002',
                    classLabel: '1B',
                    studentName: 'Bauer Berta',
                    message: 'Abweichungen: Aktuelle',
                },
            ],
            invalidStudentV3TestSummaries: [
                {
                    key: '3003',
                    classLabel: '1C',
                    studentName: 'Celik Cem',
                    message: 'Semester fehlt.',
                },
            ],
            studentV3TestStatusPresentation: vi.fn(student => ({
                'data-test-status': {
                    1001: 'valid',
                    2002: 'failed',
                    3003: 'invalid-data',
                }[student.student_code],
            })),
            downloadBlob,
        }

        expect(methods.studentV3TestSummaryPdfPayload.call(context)).toEqual(expectedPayload)

        vi.stubGlobal('axios', { post })

        try {
            await methods.downloadStudentV3TestSummaryPdf.call(context)
        } finally {
            vi.unstubAllGlobals()
        }

        expect(post).toHaveBeenCalledWith(
            '/api/admin/students-timetables/tests-v3/summary/pdf',
            expectedPayload,
            { responseType: 'blob' },
        )
        expect(downloadBlob).toHaveBeenCalledWith(pdfBlob, 'tests-v3.pdf')
        expect(context.studentV3TestSummaryPdfExporting).toBe(false)
    })

    it('resets the PDF loading state when generation fails', async () => {
        const methods = (TestsV3 as any).methods
        const error = new Error('PDF failed')
        const post = vi.fn().mockRejectedValue(error)
        const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})
        const alert = vi.fn()
        const context: any = {
            ...methods,
            completedStudentV3TestCount: 1,
            studentV3TestSummaryPdfExporting: false,
            studentV3TestSummaryPdfPayload: vi.fn().mockReturnValue({ results: [] }),
            downloadBlob: vi.fn(),
        }

        vi.stubGlobal('axios', { post })
        vi.stubGlobal('alert', alert)

        try {
            await methods.downloadStudentV3TestSummaryPdf.call(context)
        } finally {
            vi.unstubAllGlobals()
            consoleError.mockRestore()
        }

        expect(context.studentV3TestSummaryPdfExporting).toBe(false)
        expect(post).toHaveBeenCalledOnce()
        expect(alert).toHaveBeenCalledWith('Das PDF konnte nicht erstellt werden.')
    })
})
