import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import Timetable from '@/pages/admin/studentsTimetables/timetable/Timetable.vue'

describe('Students timetable timetable page', () => {
    it('shows the timetable overview on the timetable overview subpage', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetable/Timetable.vue',
            'utf8',
        )

        expect(componentSource).toContain(
            "const Overview = defineAsyncComponent(() => import('../overview/Overview.vue'))",
        )
        expect(componentSource).toContain('<Overview v-if="subAction === \'overview\'" />')
        expect(componentSource).not.toContain("import RobotTimetable from '../robot/RobotTimetable.vue'")
        expect(componentSource).not.toContain('<RobotTimetable v-else-if="subAction === \'robot\'" />')
        expect(componentSource).not.toContain("{ key: 'overview', label: 'Übersicht' }")
        expect(componentSource).not.toContain("{ key: 'robot', label: 'Wizzard' }")
        expect(componentSource).not.toContain("{ key: 'imports', label: 'Importe' }")
        expect(componentSource).toContain("const allowed = this.canManageTimetableImports")
        expect(componentSource).toContain('configuredRolesLoaded()')
        expect(componentSource).toContain('syncRouteStateFromParams()')
        expect(componentSource).toContain("['overview', 'imports']")
        expect(componentSource).toContain("['overview']")
        expect(componentSource).toContain('redirectUnauthorizedImportRoute()')
        expect(componentSource).toContain('redirectLegacyOverviewRoute()')
        expect(componentSource).toContain('redirectLegacyRobotRoute()')
        expect(componentSource).toContain('mounted() {\n        this.syncRouteStateFromParams()')
        expect(componentSource).toContain("this.$router.replace({ path: '/admin/students-timetables/timetable/overview' })")
        expect(componentSource).not.toContain("this.$router.replace({ path: '/admin/students-timetables' })")
        expect(componentSource).toContain("this.$router.replace({ path: '/admin/students-timetables/timetable/overview/automatic' })")
        expect(componentSource).not.toContain('v-if="showTimetableSubnav"')
        expect(componentSource).not.toContain('showTimetableSubnav()')
        expect(componentSource).not.toContain('subnavItems()')
        expect(componentSource).not.toContain('handleSubnavigation(key)')
        expect(componentSource).not.toContain('Hier entsteht das Stundenplan Center.')
    })

    it('writes the default timetable overview step into the URL', () => {
        const methods = (Timetable as any).methods
        const replace = vi.fn()
        const ctx: any = {
            $route: {
                params: {
                    section: 'timetable',
                },
            },
            $router: {
                replace,
            },
            subAction: 'imports',
            importPage: 'stundenplan',
            importSubPage: 'import',
        }

        expect(methods.redirectLegacyOverviewRoute.call(ctx)).toBe(true)
        expect(ctx.subAction).toBe('overview')
        expect(ctx.importPage).toBe('')
        expect(ctx.importSubPage).toBe('')
        expect(replace).toHaveBeenCalledWith({ path: '/admin/students-timetables/timetable/overview' })
    })

    it('keeps the imports route active while role config is still loading', () => {
        const methods = (Timetable as any).methods
        const replace = vi.fn()
        const ctx: any = {
            $route: {
                params: {
                    subsection: 'imports',
                },
            },
            $router: {
                replace,
            },
            canManageTimetableImports: false,
            configuredRolesLoaded: false,
            subAction: 'imports',
            importPage: '',
            importSubPage: '',
        }

        methods.redirectUnauthorizedImportRoute.call(ctx)

        expect(ctx.subAction).toBe('imports')
        expect(replace).not.toHaveBeenCalled()
    })

    it('restores the direct imports route after admin roles load', () => {
        const methods = (Timetable as any).methods
        const roleWatcher = (Timetable as any).watch.configuredRoleNames
        const ctx: any = {
            ...methods,
            $route: {
                params: {
                    section: 'timetable',
                    subsection: 'imports',
                    detail: 'stundenplan',
                    action: 'import',
                },
            },
            $router: {
                replace: vi.fn(),
            },
            canManageTimetableImports: true,
            configuredRolesLoaded: true,
            subAction: 'overview',
            importPage: '',
            importSubPage: '',
            loadImportButtonInfo: vi.fn(),
        }

        roleWatcher.call(ctx)

        expect(ctx.subAction).toBe('imports')
        expect(ctx.importPage).toBe('stundenplan')
        expect(ctx.importSubPage).toBe('import')
        expect(ctx.loadImportButtonInfo).toHaveBeenCalledOnce()
        expect(ctx.$router.replace).not.toHaveBeenCalled()
    })

    it('restores the direct imports route when the component mounts with roles already loaded', () => {
        const methods = (Timetable as any).methods
        const ctx: any = {
            ...methods,
            $route: {
                params: {
                    section: 'timetable',
                    subsection: 'imports',
                },
            },
            $router: {
                replace: vi.fn(),
            },
            canManageTimetableImports: true,
            configuredRolesLoaded: true,
            subAction: 'overview',
            importPage: '',
            importSubPage: '',
            loadImportButtonInfo: vi.fn(),
        }

        methods.syncRouteStateFromParams.call(ctx)

        expect(ctx.subAction).toBe('imports')
        expect(ctx.importPage).toBe('')
        expect(ctx.importSubPage).toBe('')
        expect(ctx.loadImportButtonInfo).toHaveBeenCalledOnce()
        expect(ctx.$router.replace).not.toHaveBeenCalled()
    })

    it('shows import buttons that open import subpages', () => {
        const componentSource = readFileSync(
            'resources/js/pages/admin/studentsTimetables/timetable/Timetable.vue',
            'utf8',
        )
        const routeSource = readFileSync('resources/routes/admin.js', 'utf8')
        const fileUploadSource = readFileSync('resources/js/pages/components/FileUpload.vue', 'utf8')

        expect(componentSource).toContain('v-if="subAction === \'imports\' && !activeImportPage"')
        expect(componentSource).toContain('Importe')
        expect(componentSource).toContain('<span>Importe:</span>')
        expect(componentSource).toContain('{{ personalImportSchoolyearLabel }}')
        expect(componentSource).toContain('class="text-h6 font-weight-bold text-primary"')
        expect(componentSource).toContain('importButtons()')
        expect(componentSource).toContain("label: 'Stundenplan'")
        expect(componentSource).toContain("label: 'Anrechnungen'")
        expect(componentSource).toContain("label: 'Sokrates 116'")
        expect(componentSource).not.toContain("key: 'faecher'")
        expect(componentSource).toContain(':prepend-icon="button.icon"')
        expect(componentSource).toContain('/api/admin/students-timetables/imports')
        expect(componentSource).not.toContain('subjectImportIndexRoute')
        expect(componentSource).toContain('Letzter Import:')
        expect(componentSource).toContain('@click="openImportPage(button.key)"')
        expect(componentSource).toContain('openImportPage(key)')
        expect(componentSource).toContain('/admin/students-timetables/timetable/imports/${this.importPage}')
        expect(componentSource).toContain('activeImportButton')
        expect(componentSource).toContain('st-import-page-title')
        expect(componentSource).toContain('class="st-import-back-button"')
        expect(componentSource).toContain('margin-left: auto')
        expect(componentSource.indexOf('class="st-import-back-button"'))
            .toBeLessThan(componentSource.indexOf('@click="closeImportPage"'))
        expect(componentSource).toContain('st-import-file-info-card')
        expect(componentSource).toContain('Benötigte Importdatei')
        expect(componentSource.indexOf('<span class="st-import-page-title__label">{{ activeImportButton.label }}</span>'))
            .toBeLessThan(componentSource.indexOf('<span class="font-weight-bold">Benötigte Importdatei</span>'))
        expect(componentSource).toContain('st-import-file-info-card__button')
        expect(componentSource).toContain('@click="closeImportPage"')
        expect(componentSource).toContain('to="/admin/students-timetables/timetable/imports/stundenplan/import"')
        expect(componentSource.indexOf('class="st-import-file-info-card__button"'))
            .toBeLessThan(componentSource.indexOf('to="/admin/students-timetables/timetable/imports/stundenplan/import"'))
        expect(componentSource).toContain('activeImportSubPage === \'import\'')
        expect(componentSource).toContain('TXT-Datei importieren')
        expect(componentSource).not.toContain('JSON-Datei importieren')
        expect(componentSource).toContain('activeImportUploadTitle')
        expect(componentSource).toContain('activeImportUploadPath')
        expect(componentSource).toContain('activeImportAllowedFileTypes')
        expect(componentSource).toContain('activeImportUploadVisible')
        expect(componentSource).toContain('Schuljahr ändern')
        expect(componentSource).toContain('@click.prevent="openSchoolyearEdit"')
        expect(componentSource).toContain('v-model="schoolyearDialog"')
        expect(componentSource).toContain('saveSchoolyear')
        expect(componentSource).toContain('useSchoolyearStore')
        expect(componentSource).toContain('useValidationRulesSetup')
        expect(componentSource).toContain('/api/admin/students-timetables/upload')
        expect(componentSource).not.toContain('subjectUploadRoute')
        expect(componentSource).not.toContain("['application/json']")
        expect(componentSource).toContain("['text/plain']")
        expect(componentSource).toContain('activeImportUploadSuccessLabel')
        expect(componentSource).toContain('refreshFilePond')
        expect(componentSource).toContain('onImportUploadStart')
        expect(componentSource).toContain('onUploadFinished')
        expect(componentSource).toContain('onUploadError')
        expect(componentSource).toContain('TXT-Datei (.txt)')
        expect(componentSource).toContain('Untis-Export')
        expect(componentSource).toContain('Tabstopps / tabulatorgetrennt')
        expect(componentSource).toContain('TT-Einträge mit Stundenplan-Zeilen')
        expect(componentSource).toContain('Folgende Einträge dürfen in der Untis-Datei enthalten sein:')
        expect(componentSource).toContain('mdi-information-outline')
        expect(componentSource).not.toContain('<span>Info:</span>')
        expect(componentSource).toContain('allowedUntisEntryTypes')
        expect(componentSource).toContain('st-import-file-info-note')
        expect(componentSource).toContain('st-import-history-card')
        expect(componentSource).toContain('Importverlauf')
        expect(componentSource).toContain("import { downloadSource as downloadTimetableImportSource } from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/TimetableImportController'")
        expect(componentSource).toContain("import { downloadSource as downloadRecognitionImportSource } from '@/actions/App/Http/Controllers/Admin/StudentsTimetables/RecognitionCsvUploadController'")
        expect(componentSource).toContain("import { downloadSource as downloadImport116Source } from '@/actions/App/Http/Controllers/Admin/Teaching/Import116Controller'")
        expect(componentSource).toContain(':href="timetableSourceDownloadUrl(importItem)"')
        expect(componentSource).toContain(':href="recognitionSourceDownloadUrl(importItem)"')
        expect(componentSource).toContain(':href="import116SourceDownloadUrl(run)"')
        expect(componentSource).toContain('mdi-download-outline')
        expect(componentSource).toContain('Quelldatei nicht mehr verfügbar')
        expect(componentSource.indexOf('@click.stop="openDeleteDialog(importItem)"'))
            .toBeLessThan(componentSource.indexOf(':href="timetableSourceDownloadUrl(importItem)"'))
        expect(componentSource.indexOf('@click.stop="openRecognitionDeleteDialog(importItem)"'))
            .toBeLessThan(componentSource.indexOf(':href="recognitionSourceDownloadUrl(importItem)"'))
        expect(componentSource.indexOf('@click.stop="import116OpenDeleteDialog(run)"'))
            .toBeLessThan(componentSource.indexOf(':href="import116SourceDownloadUrl(run)"'))
        expect(componentSource).toContain("const shouldLoadFullTimetableImports = this.activeImportPage === 'stundenplan'")
        expect(componentSource).toContain("const shouldLoadFullRecognitionImports = this.activeImportPage === 'anrechnungen'")
        expect(componentSource).toContain('per_page: shouldLoadFullTimetableImports ? 100 : 1')
        expect(componentSource).toContain('summary: shouldLoadFullTimetableImports ? 0 : 1')
        expect(componentSource).toContain('summary: shouldLoadFullRecognitionImports ? 0 : 1')
        expect(componentSource).toContain('openDeleteDialog(importItem)')
        expect(componentSource).toContain('deleteImport()')
        expect(componentSource).toContain('Import löschen')
        expect(componentSource).toContain('sectionLabel(code)')
        expect(componentSource).toContain('statusText(importItem)')
        expect(componentSource).toContain('importProgress(importItem)')
        expect(componentSource).toContain('updatePolling()')
        expect(componentSource).toContain('st-main-dataset-summary')
        expect(componentSource).toContain('redirectRemovedSubjectImportRoute()')
        expect(componentSource).not.toContain('subjectImportPrompt')
        expect(componentSource).not.toContain('activeSubjectImport')
        expect(componentSource).not.toContain('subjectDataset')
        expect(componentSource).not.toContain('student_timetable_subject_rows')
        expect(componentSource).not.toContain('/subjects-overview-json')
        expect(componentSource).toContain("activeImportPage === 'anrechnungen'")
        expect(componentSource).toContain('CSV-Datei (.csv)')
        expect(componentSource).toContain('Sokrates Bund')
        expect(componentSource).toContain('Studierende, Fächer, Noten')
        expect(componentSource).toContain('to="/admin/students-timetables/timetable/imports/anrechnungen/import"')
        expect(componentSource).toContain('CSV-Datei importieren')
        expect(componentSource).toContain('Anrechnungen · Sokrates Bund')
        expect(componentSource).toContain('/api/admin/students-timetables/recognitions-csv')
        expect(componentSource).toContain('recognitionImports')
        expect(componentSource).toContain('recognitionsResponse.data?.data || []')
        expect(componentSource).toContain("activeImportPage === 'anrechnungen' && activeRecognitionDataset")
        expect(componentSource).toContain('student_timetable_recognition_rows')
        expect(componentSource).toContain('recognitionDatasetSummaryItems')
        expect(componentSource).toContain("activeImportPage === 'anrechnungen' && activeRecognitionSubjectGradeCounts.length")
        expect(componentSource).toContain('activeRecognitionSubjectGradeCounts')
        expect(componentSource).toContain('activeRecognitionGradeCounts')
        expect(componentSource).toContain("activeImportPage === 'anrechnungen' && activeRecognitionTeacherCodes.length")
        expect(componentSource).toContain('activeRecognitionTeacherCodes')
        expect(componentSource).toContain('recognitionTeacherTotal(activeRecognitionTeacherCodes)')
        expect(componentSource).toContain('recognitionsResponse.data?.active_dataset || null')
        expect(componentSource).toContain(`if (this.activeImportPage === 'anrechnungen') {
                this.uploadedFilename = file?.name || 'gespeichert'
                this.refreshFilePond++
                this.loadImportButtonInfo()
                this.closeImportUploadPage()`)
        expect(componentSource).toContain('recognitionStatusText(importItem)')
        expect(componentSource).toContain('recognitionStatusColor(importItem)')
        expect(componentSource).toContain('recognitionImportIsProcessing(importItem)')
        expect(componentSource).toContain('v-if="recognitionImports.length" variant="accordion" multiple')
        expect(componentSource).toContain('<div class="st-import-history-meta-section">Gesamt</div>')
        expect(componentSource).toContain('Gesamtzeilen')
        expect(componentSource).toContain('Importierte Zeilen')
        expect(componentSource).toContain('Importierte Studierende')
        expect(componentSource).not.toContain('Studierende ohne Noten')
        expect(componentSource).toContain('Übersprungene Zeilen')
        expect(componentSource).toContain('st-import-history-meta-section')
        expect(componentSource).not.toContain('st-import-history-subject-panels')
        expect(componentSource).not.toContain('st-import-history-subject-title')
        expect(componentSource).not.toContain('st-import-history-teacher-panels')
        expect(componentSource).not.toContain('<v-expansion-panels flat variant="accordion" class="st-import-history-teacher-panels">')
        expect(componentSource).not.toContain('Fächer relativ')
        expect(componentSource).toContain('Importierte Noten')
        expect(componentSource).toContain('Noten N')
        expect(componentSource).toContain('Noten B')
        expect(componentSource).toContain('Noten 1-4')
        expect(componentSource).toContain('Noten 5')
        expect(componentSource).toContain('Sonstige Noten')
        expect(componentSource).not.toContain('Anzahl {{ importItem.imported_subjects_count || 0 }}')
        expect(componentSource).not.toContain('<v-expansion-panels flat variant="accordion" class="st-import-history-subject-panels">')
        expect(componentSource).toContain('<v-expansion-panel-title>')
        expect(componentSource).toContain('<v-expansion-panel-text>')
        expect(componentSource).not.toContain('v-if="importItem.subject_grade_counts?.length"')
        expect(componentSource).toContain('class="st-import-history-subject-table"')
        expect(componentSource).toContain('Fach')
        expect(componentSource).toContain('<th class="text-right">1-4</th>')
        expect(componentSource).toContain('<th class="text-right">5</th>')
        expect(componentSource).toContain('<th class="text-right">N</th>')
        expect(componentSource).toContain('<v-icon icon="mdi-sigma" size="14" title="Summe" />')
        expect(componentSource).toContain('<th class="text-right">A</th>')
        expect(componentSource).toContain('<th class="text-right">B</th>')
        expect(componentSource).not.toContain('<template v-for="subjectItem in importItem.subject_grade_counts" :key="subjectItem.subject">')
        expect(componentSource).toContain('v-for="subjectItem in activeRecognitionSubjectGradeCounts"')
        expect(componentSource).toContain('{{ subjectItem.subject }}')
        expect(componentSource).toContain('{{ subjectItem.one_to_four_count || 0 }}')
        expect(componentSource).toContain('{{ subjectItem.b_count || 0 }}')
        expect(componentSource).toContain('{{ subjectItem.five_count || 0 }}')
        expect(componentSource).toContain('{{ subjectItem.n_count || 0 }}')
        expect(componentSource).toContain('{{ subjectItem.other_count || 0 }}')
        expect(componentSource).toContain('{{ recognitionSubjectCountedTotal(subjectItem) }}')
        expect(componentSource).toContain('st-import-history-subject-total-cell')
        expect(componentSource).toContain(':deep(th:not(:last-child))')
        expect(componentSource).toContain('border-right: 1px solid rgba(25, 118, 210, 0.16);')
        expect(componentSource).toContain('st-import-history-subject-percent-row')
        expect(componentSource).toContain('recognitionSubjectPercentage(subjectItem.one_to_four_count, recognitionSubjectCountedTotal(subjectItem))')
        expect(componentSource).not.toContain('recognitionSubjectPercentage(recognitionSubjectCountedTotal(subjectItem), recognitionSubjectCountedTotal(subjectItem))')
        expect(componentSource).toContain('st-import-history-subject-sum-row')
        expect(componentSource).toContain('st-import-history-subject-sum-percent-row')
        expect(componentSource).not.toContain('recognitionGradeCountedTotal(importItem.grade_counts)')
        expect(componentSource).toContain('recognitionGradeCountedTotal(activeRecognitionGradeCounts)')
        expect(componentSource).toContain('recognitionSubjectPercentage(activeRecognitionGradeCounts?.one_to_four, recognitionGradeCountedTotal(activeRecognitionGradeCounts))')
        expect(componentSource).not.toContain('recognitionSubjectPercentage(recognitionGradeCountedTotal(activeRecognitionGradeCounts), recognitionGradeCountedTotal(activeRecognitionGradeCounts))')
        expect(componentSource).not.toContain('recognitionSubjectPercentage(subjectItem.other_count, recognitionSubjectCountedTotal(subjectItem))')
        expect(componentSource).not.toContain('recognitionSubjectPercentage(subjectItem.b_count, recognitionSubjectCountedTotal(subjectItem))')
        expect(componentSource).not.toContain('recognitionSubjectPercentage(importItem.grade_counts?.other, recognitionGradeCountedTotal(importItem.grade_counts))')
        expect(componentSource).not.toContain('recognitionSubjectPercentage(importItem.grade_counts?.b, recognitionGradeCountedTotal(importItem.grade_counts))')
        expect(componentSource).toContain('Summe')
        expect(componentSource).not.toContain('Meiste N')
        expect(componentSource).not.toContain('Meiste B')
        expect(componentSource).not.toContain('Meiste 1-4')
        expect(componentSource).not.toContain('Meiste 5')
        expect(componentSource).toContain('importItem.total_rows || 0')
        expect(componentSource).toContain('importItem.imported_rows || 0')
        expect(componentSource).toContain('importItem.imported_students_count || 0')
        expect(componentSource).not.toContain('importItem.students_without_grades_count || 0')
        expect(componentSource).toContain('importItem.skipped_rows || 0')
        expect(componentSource).toContain('importItem.grade_counts?.total || 0')
        expect(componentSource).toContain('importItem.grade_counts?.n || 0')
        expect(componentSource).toContain('importItem.grade_counts?.b || 0')
        expect(componentSource).toContain('importItem.grade_counts?.one_to_four || 0')
        expect(componentSource).toContain('importItem.grade_counts?.five || 0')
        expect(componentSource).toContain('importItem.grade_counts?.other || 0')
        expect(componentSource).not.toContain('importItem.imported_subjects_count || 0')
        expect(componentSource).not.toContain('Anzahl {{ importItem.imported_teachers_count || 0 }}')
        expect(componentSource).not.toContain('v-if="importItem.teacher_codes?.length"')
        expect(componentSource).toContain('class="st-import-history-teacher-table"')
        expect(componentSource).toContain('Alle Lehrer')
        expect(componentSource).toContain('<th class="text-right">1-4</th>')
        expect(componentSource).not.toContain('<template v-for="teacherItem in importItem.teacher_codes" :key="teacherItem.code">')
        expect(componentSource).toContain('v-for="teacherItem in activeRecognitionTeacherCodes"')
        expect(componentSource).toContain(':key="teacherItem.code"')
        expect(componentSource).toContain('{{ teacherItem.code }}')
        expect(componentSource).toContain('{{ teacherItem.one_to_four_count || 0 }}')
        expect(componentSource).toContain('{{ teacherItem.five_count || 0 }}')
        expect(componentSource).toContain('{{ teacherItem.n_count || 0 }}')
        expect(componentSource).toContain('{{ recognitionTeacherCountedTotal(teacherItem) }}')
        expect(componentSource).toContain('st-import-history-teacher-percent-row')
        expect(componentSource).toContain('st-import-history-teacher-subjects-cell')
        expect(componentSource).toContain('{{ recognitionTeacherSubjectsLabel(teacherItem) }}')
        expect(componentSource).toContain('color: rgba(0, 0, 0, 0.5);')
        expect(componentSource).toContain('font-size: 0.68rem;')
        expect(componentSource).toContain('padding: 0 4px !important;')
        expect(componentSource).toContain('padding: 0 2px !important;')
        expect(componentSource).toContain('recognitionSubjectPercentage(teacherItem.one_to_four_count, recognitionTeacherCountedTotal(teacherItem))')
        expect(componentSource).toContain('recognitionSubjectPercentage(teacherItem.five_count, recognitionTeacherCountedTotal(teacherItem))')
        expect(componentSource).toContain('recognitionSubjectPercentage(teacherItem.n_count, recognitionTeacherCountedTotal(teacherItem))')
        expect(componentSource).not.toContain('recognitionSubjectPercentage(recognitionTeacherCountedTotal(teacherItem), recognitionTeacherCountedTotal(teacherItem))')
        expect(componentSource).toContain('{{ teacherItem.other_count || 0 }}')
        expect(componentSource).toContain('{{ teacherItem.b_count || 0 }}')
        expect(componentSource).toContain('st-import-history-teacher-divider')
        expect(componentSource).toContain('st-import-history-teacher-total-cell')
        expect(componentSource).toContain('st-import-history-teacher-sum-row')
        expect(componentSource).not.toContain('recognitionTeacherOneToFourTotal(importItem.teacher_codes)')
        expect(componentSource).not.toContain('recognitionTeacherFiveTotal(importItem.teacher_codes)')
        expect(componentSource).not.toContain('recognitionTeacherNTotal(importItem.teacher_codes)')
        expect(componentSource).not.toContain('recognitionTeacherTotal(importItem.teacher_codes)')
        expect(componentSource).not.toContain('recognitionTeacherOtherTotal(importItem.teacher_codes)')
        expect(componentSource).not.toContain('recognitionTeacherBTotal(importItem.teacher_codes)')
        expect(componentSource).toContain('recognitionTeacherOneToFourTotal(activeRecognitionTeacherCodes)')
        expect(componentSource).toContain('recognitionTeacherFiveTotal(activeRecognitionTeacherCodes)')
        expect(componentSource).toContain('recognitionTeacherNTotal(activeRecognitionTeacherCodes)')
        expect(componentSource).toContain('recognitionTeacherTotal(activeRecognitionTeacherCodes)')
        expect(componentSource).toContain('recognitionTeacherOtherTotal(activeRecognitionTeacherCodes)')
        expect(componentSource).toContain('recognitionTeacherBTotal(activeRecognitionTeacherCodes)')
        expect(componentSource).toContain('st-import-history-teacher-sum-percent-row')
        expect(componentSource).toContain('recognitionSubjectPercentage(recognitionTeacherOneToFourTotal(activeRecognitionTeacherCodes), recognitionTeacherTotal(activeRecognitionTeacherCodes))')
        expect(componentSource).not.toContain('recognitionSubjectPercentage(recognitionTeacherTotal(importItem.teacher_codes), recognitionTeacherTotal(importItem.teacher_codes))')
        expect(componentSource).not.toContain('importItem.subject_grade_counts')
        expect(componentSource).toContain('st-import-history-label-col')
        expect(componentSource).toContain('st-import-history-count-col')
        expect(componentSource).toContain('width: 150px;')
        expect(componentSource).toContain('min-width: 150px;')
        expect(componentSource).toContain('max-width: 150px;')
        expect(componentSource).toContain('width: 48px;')
        expect(componentSource).toContain('width: 100%;')
        expect(componentSource).not.toContain('max-width: 430px;')
        expect(componentSource).toContain('recognitionOtherGradesLabel(importItem.grade_counts?.other_details)')
        expect(componentSource).toContain(
            'v-if="recognitionOtherGradesLabel(importItem.grade_counts?.other_details) !== \'-\'"',
        )
        expect(componentSource).toContain('({{ recognitionOtherGradesLabel(importItem.grade_counts?.other_details) }})')
        expect(componentSource).toContain('st-import-history-meta-item small')
        expect(componentSource).toContain('st-import-history-inline-detail')
        expect(componentSource).toContain('.st-import-history-meta-item .st-import-history-inline-detail')
        expect(componentSource).toContain('display: inline;')
        expect(componentSource).not.toContain('recognitionSubjectLeaderLabel(')
        expect(componentSource).not.toContain('recognitionSubjectRelativeLeaderLabel(')
        expect(componentSource).toContain('importItem.grade_counts?.one_to_four')
        expect(componentSource).toContain('importItem.grade_counts?.b')
        expect(componentSource).toContain('importItem.grade_counts?.five')
        expect(componentSource).toContain('importItem.grade_counts?.n')
        expect(componentSource.indexOf('<div class="st-import-history-meta-section">Gesamt</div>'))
            .toBeLessThan(componentSource.indexOf('Gesamtzeilen'))
        expect(componentSource.indexOf('importItem.skipped_rows || 0'))
            .toBeLessThan(componentSource.indexOf('importItem.imported_students_count || 0'))
        expect(componentSource.indexOf('importItem.imported_students_count || 0'))
            .toBeLessThan(componentSource.indexOf('Importierte Noten'))
        expect(componentSource.indexOf('Importierte Noten'))
            .toBeLessThan(componentSource.indexOf('Noten 1-4'))
        expect(componentSource.indexOf('Noten 1-4'))
            .toBeLessThan(componentSource.indexOf('Noten B'))
        expect(componentSource.indexOf('Noten B'))
            .toBeLessThan(componentSource.indexOf('Noten 5'))
        expect(componentSource.indexOf('Noten 5'))
            .toBeLessThan(componentSource.indexOf('Noten N'))
        expect(componentSource.indexOf('Noten N'))
            .toBeLessThan(componentSource.indexOf('Sonstige Noten'))
        const aggregateSubjectRowsIndex = componentSource.indexOf('v-for="subjectItem in activeRecognitionSubjectGradeCounts"')
        const aggregateSubjectPercentIndex = componentSource.indexOf('st-import-history-subject-percent-row', aggregateSubjectRowsIndex)
        const aggregateSubjectSumIndex = componentSource.indexOf('st-import-history-subject-sum-row', aggregateSubjectPercentIndex)
        const aggregateSubjectSumPercentIndex = componentSource.indexOf('st-import-history-subject-sum-percent-row', aggregateSubjectSumIndex)

        expect(aggregateSubjectRowsIndex).toBeLessThan(aggregateSubjectPercentIndex)
        expect(aggregateSubjectPercentIndex).toBeLessThan(aggregateSubjectSumIndex)
        expect(aggregateSubjectSumIndex).toBeLessThan(aggregateSubjectSumPercentIndex)
        const subjectSumHeaderIndex = componentSource.indexOf('<v-icon icon="mdi-sigma" size="14" title="Summe" />')
        const subjectOtherHeaderIndex = componentSource.indexOf('<th class="text-right">A</th>', subjectSumHeaderIndex)
        const subjectBHeaderIndex = componentSource.indexOf('<th class="text-right">B</th>', subjectOtherHeaderIndex)

        expect(subjectSumHeaderIndex).toBeLessThan(subjectOtherHeaderIndex)
        expect(subjectOtherHeaderIndex).toBeLessThan(subjectBHeaderIndex)
        expect(componentSource).toContain('Abgeschlossen')
        expect(componentSource).toContain('Fehlgeschlagen')
        expect(componentSource).toContain('Wartet')
        expect(componentSource).toContain('Läuft')
        expect(componentSource).toContain('Import wird verarbeitet.')
        expect(componentSource).toContain('recognitionImports.some(importItem => this.recognitionImportIsProcessing(importItem))')
        expect(componentSource).toContain('Anrechnungs-Import löschen')
        expect(componentSource).toContain('Danach kann diese Datei erneut importiert werden.')
        expect(componentSource).toContain('openRecognitionDeleteDialog(importItem)')
        expect(componentSource).toContain('deleteRecognitionImport()')
        expect(componentSource).toContain('/api/admin/students-timetables/recognitions-csv/${this.recognitionDeleteTargetImport.id}')
        expect(componentSource).toContain('recognitionImportDetail')
        expect(componentSource).toContain('importItem?.imported_rows')
        expect(componentSource).toContain("if (this.activeImportPage === 'anrechnungen') return true")
        expect(componentSource).toContain("if (this.activeImportPage === 'anrechnungen') return 'CSV-Datei'")
        expect(componentSource).toContain("this.uploadError = serverMessage || 'Die CSV-Datei konnte nicht gespeichert werden.'")
        expect(componentSource).toContain('Vor der Übernahme wird geprüft, ob die Datei gültige Schülerdaten enthält.')
        expect(componentSource).toContain('Vor dem Löschen prüft das System alle verbleibenden Quelldateien.')
        expect(componentSource).toContain('@click.stop="import116OpenDeleteDialog(run)"')
        expect(componentSource).toContain('Die aktiven Schülerdaten bleiben unverändert.')
        expect(fileUploadSource).toContain('onerror: onServerError')
        expect(fileUploadSource).toContain("this.$emit('error', message)")
        expect(componentSource).toContain(':allowMultiple="activeImportUploadAllowsMultiple"')
        expect(componentSource).toContain("return this.activeImportPage === 'anrechnungen'")
        expect(componentSource).not.toContain('Anrechnungen-Konfiguration')
        expect(componentSource).not.toContain('JSON mit Schülern, Fächern und Anrechnungen')
        expect(componentSource).not.toContain('Anrechnungen, Fachzuordnungen und Gültigkeiten')
        expect(componentSource).toContain('Noch keine Anrechnungs-Datei importiert.')
        expect(componentSource).toContain('Hauptdatenbestand')
        expect(componentSource).toContain('student_timetable_entries')
        expect(componentSource).toContain('Zeitraum')
        expect(componentSource).toContain('Stundenplan-Einträge')
        expect(componentSource).toContain('Verschiedene Kurse')
        expect(componentSource).toContain('Zuletzt geändert')
        expect(componentSource).toContain('timetableResponse.data?.main_dataset')
        expect(componentSource).toContain('st-course-summary')
        expect(componentSource).toContain('<v-expansion-panels variant="accordion">')
        expect(componentSource).toContain('activeDatasetCourses')
        expect(componentSource).toContain('Alle Kurse')
        expect(componentSource).toContain('Wochenstd.')
        expect(componentSource).toContain('datasetCourseWeeklyHoursLabel(courseItem)')
        expect(componentSource).toContain('datasetCourseDateRangeLabel(courseItem)')
        expect(componentSource).toContain('st-single-date-summary')
        expect(componentSource).toContain('activeDatasetSingleDateCourses')
        expect(componentSource).toContain('activeDatasetSingleDateAppointmentsCount')
        expect(componentSource).toContain('Einzeltermine')
        expect(componentSource).toContain('singleDateAppointmentTimeLabel(appointment)')
        expect(componentSource).toContain('formatDateWithWeekdayLabel(appointment.date)')
        expect(componentSource).toContain('label="Aktiv"')
        expect(componentSource).toContain('setAllSingleDateAppointmentsActive')
        expect(componentSource).toContain('setCourseSingleDateAppointmentsActive(courseItem, $event)')
        expect(componentSource).toContain('setSingleDateAppointmentActive(courseItem, appointment, $event)')
        expect(componentSource).toContain('/api/admin/students-timetables/imports/single-date-appointments')
        expect(componentSource).toContain('singleDateActivationPayload()')
        expect(componentSource).toContain('singleDateActivationSaveInProgress')
        expect(componentSource).toContain('Änderungen werden im Hintergrund gespeichert.')
        expect(componentSource).toContain('queueSingleDateAppointmentActivationSave()')
        expect(routeSource).toContain('/admin/students-timetables/:section?/:subsection?/:detail?/:action?')
    })

    it('shows the personal target schoolyear for all import types', () => {
        const computed = (Timetable as any).computed

        expect(computed.personalImportSchoolyearLabel.call({
            config: {
                schoolwide_active_schoolyear: { id: 12, concerns: '2026/27' },
                selected_schoolyear: { id: 11, concerns: '2025/26' },
            },
        })).toBe('2025/26')
    })

    it('shows a failed Sokrates import as an error without updating the last import time', async () => {
        const methods = (Timetable as any).methods
        const loadRuns = vi.fn().mockResolvedValue(undefined)
        const ctx: any = {
            import116Importing: true,
            import116LastImportAt: null,
            import116RunActionMessage: 'Alter Erfolg',
            import116RunActionError: '',
            import116LoadRuns: loadRuns,
        }

        await methods.handleImport116Finished.call(ctx, {
            detail: {
                status: 422,
                message: 'Die Datei enthält keine gültigen Schülerdaten.',
                data: { created: 0, updated: 0, deleted: 0 },
            },
        })

        expect(ctx.import116Importing).toBe(false)
        expect(ctx.import116LastImportAt).toBeNull()
        expect(ctx.import116RunActionMessage).toBe('')
        expect(ctx.import116RunActionError).toBe('Die Datei enthält keine gültigen Schülerdaten.')
        expect(loadRuns).toHaveBeenCalledOnce()
    })

    it('keeps the timetable delete dialog open when the server rejects rebuilding', async () => {
        const methods = (Timetable as any).methods
        const globalScope = globalThis as any
        const originalAxios = globalScope.axios
        globalScope.axios = {
            delete: vi.fn().mockRejectedValue({
                response: { data: { message: 'Die verbleibende Quelldatei fehlt. Es wurde nichts gelöscht.' } },
            }),
        }
        const ctx: any = {
            deleteTargetImport: { id: 17 },
            deleteDialog: true,
            deleting: false,
            deleteError: '',
            loadImportButtonInfo: vi.fn(),
        }

        try {
            await methods.deleteImport.call(ctx)
        } finally {
            globalScope.axios = originalAxios
        }

        expect(ctx.deleteDialog).toBe(true)
        expect(ctx.deleteTargetImport).toEqual({ id: 17 })
        expect(ctx.deleteError).toBe('Die verbleibende Quelldatei fehlt. Es wurde nichts gelöscht.')
        expect(ctx.deleting).toBe(false)
        expect(ctx.loadImportButtonInfo).not.toHaveBeenCalled()
    })

    it('formats import button metadata', () => {
        const methods = (Timetable as any).methods

        expect(methods.importedTtCount({
            sections: { TT: 9 },
            tt_skipped_invalid: 2,
        })).toBe(7)

        expect(methods.formatFileSize(1536)).toBe('1.5 KB')

        expect(methods.dateRangeLabel({
            tt_first_date: '2026-02-16',
            tt_last_date: '2026-07-11',
        })).toBe('2026-02-16 - 2026-07-11')

        expect(methods.sectionLabel('TT')).toBe('Stundenplan-Einträge')
        expect((Timetable as any).computed.allowedUntisEntryTypes()).toBe('VV, SU, TE, RM, KL, GR, LS, TT')
        expect(methods.statusText({ import_status: 'completed' })).toBe('Abgeschlossen')
        expect(methods.importProgress({ progress_current: 25, progress_total: 100 })).toBe(25)
        expect(methods.recognitionOtherGradesLabel([{ note: 'A', count: 2 }, { note: 'X', count: 1 }]))
            .toBe('A: 2 · X: 1')
        expect(methods.recognitionOtherGradesLabel([])).toBe('-')
        expect(methods.recognitionSubjectPercentage(2, 8)).toBe('25.0%')
        expect(methods.recognitionSubjectPercentage(0, 0)).toBe('-')
        expect(methods.recognitionSubjectCountedTotal({
            one_to_four_count: 2,
            five_count: 1,
            n_count: 3,
            other_count: 4,
            b_count: 5,
        })).toBe(6)
        expect(methods.recognitionGradeCountedTotal({
            one_to_four: 20,
            five: 4,
            n: 6,
            other: 8,
            b: 10,
        })).toBe(30)
        expect(methods.recognitionTeacherOneToFourTotal([
            { one_to_four_count: 2 },
            { one_to_four_count: 3 },
            {},
        ])).toBe(5)
        expect(methods.recognitionTeacherOneToFourTotal(null)).toBe(0)
        expect(methods.recognitionTeacherFiveTotal([
            { five_count: 1 },
            { five_count: 4 },
            {},
        ])).toBe(5)
        expect(methods.recognitionTeacherFiveTotal(null)).toBe(0)
        expect(methods.recognitionTeacherNTotal([
            { n_count: 2 },
            { n_count: 4 },
            {},
        ])).toBe(6)
        expect(methods.recognitionTeacherNTotal(null)).toBe(0)
        expect(methods.recognitionTeacherCountedTotal({
            one_to_four_count: 2,
            five_count: 1,
            n_count: 3,
        })).toBe(6)
        expect(methods.recognitionTeacherTotal([
            { one_to_four_count: 2, five_count: 1, n_count: 3 },
            { one_to_four_count: 4, five_count: 0, n_count: 2 },
            {},
        ])).toBe(12)
        expect(methods.recognitionTeacherTotal(null)).toBe(0)
        expect(methods.recognitionTeacherOtherTotal([
            { other_count: 2 },
            { other_count: 3 },
            {},
        ])).toBe(5)
        expect(methods.recognitionTeacherOtherTotal(null)).toBe(0)
        expect(methods.recognitionTeacherBTotal([
            { b_count: 2 },
            { b_count: 4 },
            {},
        ])).toBe(6)
        expect(methods.recognitionTeacherBTotal(null)).toBe(0)
        expect(methods.recognitionTeacherSubjectsLabel({
            subjects: ['Deutsch', 'Mathematik'],
        })).toBe('Deutsch, Mathematik')
        expect(methods.recognitionTeacherSubjectsLabel({})).toBe('')

        expect(methods.datasetDateRangeLabel({
            first_date: '2026-02-16',
            last_date: '2026-07-11',
        })).toBe('2026-02-16 - 2026-07-11')

        expect(methods.datasetCourseDateRangeLabel({
            first_date: '2026-02-16',
            last_date: '2026-07-11',
        })).toBe('2026-02-16 - 2026-07-11')

        expect(methods.datasetCourseWeeklyHoursLabel({ weekly_hours: 4 })).toBe('4')
        expect(methods.datasetCourseWeeklyHoursLabel({})).toBe('-')

        expect(methods.formatDateWithWeekdayLabel('2026-02-17')).toBe('Di, 17.02.2026')
        expect(methods.singleDateAppointmentTimeLabel({
            period: '14',
            starts_at: '20:25',
            ends_at: '21:10',
        })).toBe('14. Std. 20:25-21:10')

        const appointments = [
            {
                date: '2026-02-17',
                period: '14',
                starts_at: '20:25',
                ends_at: '21:10',
                subject: 'LPT',
                course: 'LPT',
                entry_ids: [1],
            },
            {
                date: '2026-02-18',
                period: '10',
                starts_at: '17:05',
                ends_at: '17:50',
                subject: 'LPT',
                course: 'LPT',
                entry_ids: [2],
            },
        ]
        const courseItem = { name: 'LPT-ALT', appointments }
        const firstKey = methods.singleDateAppointmentKey(courseItem, appointments[0])
        const payloadContext = {
            activeDatasetSingleDateCourses: [courseItem],
            activeSingleDateAppointmentKeySet: new Set([firstKey]),
            singleDateAppointmentKey: methods.singleDateAppointmentKey,
        }

        expect(methods.singleDateActivationPayload.call(payloadContext)).toEqual([
            { entry_ids: [1], active: true },
            { entry_ids: [2], active: false },
        ])
    })
})
