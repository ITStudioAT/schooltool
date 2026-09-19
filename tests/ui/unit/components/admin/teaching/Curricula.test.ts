import { beforeEach, describe, expect, it, vi } from 'vitest'
import Curricula from '@/pages/admin/teaching/curricula/Curricula.vue'
import CurriculumDetail from '@/pages/admin/teaching/curricula/CurriculumDetail.vue'
import CurriculaOverview from '@/pages/admin/teaching/curricula/CurriculaOverview.vue'
import { useCurriculumStore } from '@/stores/admin/teaching/CurriculumStore'

vi.mock('@/stores/admin/teaching/CurriculumStore', () => ({
    useCurriculumStore: vi.fn(),
}))

describe('Teaching curricula route sync', () => {
    beforeEach(() => {
        vi.mocked(useCurriculumStore).mockReset()
    })

    it('stores the selected curriculum in the URL query', () => {
        const routerReplace = vi.fn().mockResolvedValue(undefined)
        const ctx = {
            selectedCurriculum: null,
            $route: {
                query: {
                    page: '2',
                    search: 'deutsch',
                },
            },
            $router: {
                replace: routerReplace,
            },
            setCurriculumQuery(curriculumId: number | null) {
                return (Curricula as any).methods.setCurriculumQuery.call(this, curriculumId)
            },
        }

        ;(Curricula as any).methods.openCurriculum.call(ctx, {
            id: 15,
            title: 'Deutsch',
        })

        expect(ctx.selectedCurriculum).toEqual({
            id: 15,
            title: 'Deutsch',
        })
        expect(routerReplace).toHaveBeenCalledWith({
            query: {
                page: '2',
                search: 'deutsch',
                curriculum: '15',
            },
        })
    })

    it('removes the selected curriculum from the URL query when closing', () => {
        const routerReplace = vi.fn().mockResolvedValue(undefined)
        const ctx = {
            selectedCurriculum: {
                id: 15,
                title: 'Deutsch',
            },
            $route: {
                query: {
                    page: '2',
                    curriculum: '15',
                },
            },
            $router: {
                replace: routerReplace,
            },
            setCurriculumQuery(curriculumId: number | null) {
                return (Curricula as any).methods.setCurriculumQuery.call(this, curriculumId)
            },
        }

        ;(Curricula as any).methods.closeCurriculum.call(ctx)

        expect(ctx.selectedCurriculum).toBeNull()
        expect(routerReplace).toHaveBeenCalledWith({
            query: {
                page: '2',
            },
        })
    })

    it('restores the selected curriculum from the route query', async () => {
        const showMock = vi.fn().mockResolvedValue({
            id: 15,
            title: 'Deutsch',
        })
        const ctx = {
            curriculumStore: {
                show: showMock,
            },
            selectedCurriculum: null,
            isResolvingCurriculum: false,
            routeSyncToken: 0,
            setCurriculumQuery: vi.fn(),
        }

        await (Curricula as any).methods.syncSelectedCurriculumFromRoute.call(ctx, '15')

        expect(showMock).toHaveBeenCalledWith(15)
        expect(ctx.selectedCurriculum).toEqual({
            id: 15,
            title: 'Deutsch',
        })
        expect(ctx.isResolvingCurriculum).toBe(false)
        expect(ctx.setCurriculumQuery).not.toHaveBeenCalled()
    })

    it('clears an invalid curriculum query when the curriculum cannot be loaded', async () => {
        const setCurriculumQueryMock = vi.fn()
        const ctx = {
            curriculumStore: {
                show: vi.fn().mockResolvedValue(null),
            },
            selectedCurriculum: {
                id: 15,
                title: 'Deutsch',
            },
            isResolvingCurriculum: false,
            routeSyncToken: 0,
            setCurriculumQuery: setCurriculumQueryMock,
        }

        await (Curricula as any).methods.syncSelectedCurriculumFromRoute.call(ctx, '99')

        expect(ctx.selectedCurriculum).toBeNull()
        expect(setCurriculumQueryMock).toHaveBeenCalledWith(null)
        expect(ctx.isResolvingCurriculum).toBe(false)
    })

    it('returns from print to the selected curriculum without clearing it', () => {
        const routerReplace = vi.fn().mockResolvedValue(undefined)
        const ctx = {
            sub_action: 'print',
            selectedCurriculum: {
                id: 15,
                title: 'Deutsch',
            },
            $route: {
                query: {
                    curriculum: '15',
                    view: 'print',
                    page: '2',
                },
            },
            $router: {
                replace: routerReplace,
            },
            setViewQuery(view: string | null) {
                return (Curricula as any).methods.setViewQuery.call(this, view)
            },
        }

        ;(Curricula as any).methods.returnFromPrint.call(ctx)

        expect(ctx.sub_action).toBe('overview')
        expect(ctx.selectedCurriculum).toEqual({
            id: 15,
            title: 'Deutsch',
        })
        expect(routerReplace).toHaveBeenCalledWith({
            query: {
                curriculum: '15',
                page: '2',
            },
        })
    })

    it('falls back to the overview when the removed settings view remains in the URL', () => {
        const ctx = {
            sub_action: 'settings',
            submenuItems: [
                { key: 'print' },
            ],
        }

        ;(Curricula as any).watch['$route.query.view'].handler.call(ctx, 'settings')

        expect(ctx.sub_action).toBe('overview')
    })

    it('does not expose overview or settings submenu items', async () => {
        const data = (Curricula as any).data.call({})
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/curricula/Curricula.vue', 'utf8')
        )

        expect(data.submenuItems.map((item: { key: string }) => item.key)).toEqual(['print'])
        expect(source).toContain('<v-sheet v-if="visibleSubmenuItems.length"')
    })

    it('includes an imported curricula card with import, preview, and takeover actions', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/curricula/CurriculaOverview.vue', 'utf8')
        )

        expect(source).toContain('Importierte Curricula')
        expect(source).toContain('Curriculum importieren')
        expect(source).toContain('Vorschau')
        expect(source).toContain('toggleImportedPreview(curriculum.id)')
        expect(source).toContain('expandedImportedId === curriculum.id')
        expect(source).toContain('curricula-overview__import-preview')
        expect(source).not.toContain('formatImportAssignment(topic)')
        expect(source).toContain('importedUnitCount(curriculum)')
        expect(source).toContain('Übernehmen')
        expect(source).toContain('Importiertes Curriculum löschen')
        expect(source).toContain(':disabled="Boolean(curriculum.adopted_curriculum_id) || importedDeleteLoadingId === curriculum.id"')
        expect(source).toContain('askDeleteImportedCurriculum(curriculum)')
        expect(source).toContain('confirmDeleteImportedCurriculum')
        expect(source).toContain('importedDeleteDialogOpen')
        expect(source).toContain('importedDeleteTarget')
        expect(source).toContain('const importedDeleteId = this.importedDeleteTarget.id')
        expect(source).toContain('this.curriculumStore.destroyImportedCurriculum(importedDeleteId)')
        expect(source).toContain("import vueFilePond from 'vue-filepond/dist/vue-filepond.js'")
        expect(source).toContain("import 'filepond/dist/filepond.min.css'")
        expect(source).toContain("import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type'")
        expect(source).toContain('const FilePond = vueFilePond(FilePondPluginFileValidateType)')
        expect(source).toContain('components: { FilePond }')
        expect(source).toContain('<file-pond')
        expect(source).toContain(':instant-upload="true"')
        expect(source).toContain(':accepted-file-types="[\'application/json\', \'text/json\', \'.json\', \'application/zip\', \'application/x-zip-compressed\', \'.zip\']"')
        expect(source).toContain(':server="{ process: importPondProcess }"')
        expect(source).toContain('loadImportedCurricula()')
        expect(source).toContain('importPondProcess(fieldName, file, metadata, load, error, progress, abort)')
        expect(source).toContain('this.curriculumStore.importCurriculum(file)')
        expect(source).toContain('this.curriculumStore.adoptImportedCurriculum(curriculum.id)')
        expect(source).toContain("this.$emit('select', result)")
    })

    it('shows only the import button when there are no imported curricula', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/curricula/CurriculaOverview.vue', 'utf8')
        )

        expect(source).toContain('class="curricula-overview__import-button"')
        expect(source).toContain('v-if="imported_curricula.length"\n                    rounded="xl"')
        expect(source).not.toContain('Noch keine importierten Curricula vorhanden.')
        expect(source).not.toContain('Getrennt von deinen eigenen Curricula')
    })

    it('does not expose curriculum search controls or filtering', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/curricula/CurriculaOverview.vue', 'utf8')
        )

        expect(source).not.toContain('Curricula suchen...')
        expect(source).not.toContain('onSearchChanged')
        expect(source).not.toContain('filteredImportedCurricula')
    })

    it('toggles imported curriculum preview and summarizes assignment structure', () => {
        const ctx: Record<string, any> = {
            expandedImportedId: null,
            toggleImportedPreview(curriculumId: number) {
                return (CurriculaOverview as any).methods.toggleImportedPreview.call(this, curriculumId)
            },
            importedTopics(curriculum: Record<string, any>) {
                return (CurriculaOverview as any).methods.importedTopics.call(this, curriculum)
            },
            importedTopicUnits(topic: Record<string, any>) {
                return (CurriculaOverview as any).methods.importedTopicUnits.call(this, topic)
            },
            topicHasUnitDateAssignments(topic: Record<string, any>) {
                return (CurriculaOverview as any).methods.topicHasUnitDateAssignments.call(this, topic)
            },
            shouldShowTopicAssignmentChip(topic: Record<string, any>) {
                return (CurriculaOverview as any).methods.shouldShowTopicAssignmentChip.call(this, topic)
            },
            importedUnitCount(curriculum: Record<string, any>) {
                return (CurriculaOverview as any).methods.importedUnitCount.call(this, curriculum)
            },
            importedFreeWeekCount(curriculum: Record<string, any>) {
                return (CurriculaOverview as any).methods.importedFreeWeekCount.call(this, curriculum)
            },
            compactImportKeys(values: string[], maxVisible?: number) {
                return (CurriculaOverview as any).methods.compactImportKeys.call(this, values, maxVisible)
            },
            isoWeekFromDateKey(dateKey: string) {
                return (CurriculaOverview as any).methods.isoWeekFromDateKey.call(this, dateKey)
            },
            monthLabelFromDateKey(dateKey: string) {
                return (CurriculaOverview as any).methods.monthLabelFromDateKey.call(this, dateKey)
            },
            formatIsoWeekRanges(weeks: number[]) {
                return (CurriculaOverview as any).methods.formatIsoWeekRanges.call(this, weeks)
            },
            formatImportAssignment(item: Record<string, any>) {
                return (CurriculaOverview as any).methods.formatImportAssignment.call(this, item)
            },
        }

        const importedCurriculum = {
            topics: [
                {
                    id: 'topic-1',
                    title: 'Thema 1',
                    assignment_type: 'month',
                    month_keys: ['2026-09', '2026-10'],
                    units: [
                        {
                            id: 'unit-1',
                            title: 'Einheit 1',
                            assignment_type: 'weeks',
                            week_keys: ['2026-09-07', '2026-09-14', '2026-09-21', '2026-09-28'],
                        },
                    ],
                },
                {
                    id: 'topic-2',
                    title: 'Thema 2',
                    assignment_type: 'all_weeks',
                    units: [
                        {
                            id: 'unit-2',
                            title: 'Einheit 2',
                            assignment_type: 'none',
                        },
                    ],
                },
            ],
        }

        ctx.toggleImportedPreview(31)
        expect(ctx.expandedImportedId).toBe(31)
        ctx.toggleImportedPreview(31)
        expect(ctx.expandedImportedId).toBeNull()

        expect(ctx.importedTopics(importedCurriculum)).toHaveLength(2)
        expect(ctx.importedTopicUnits(importedCurriculum.topics[0])).toHaveLength(1)
        expect(ctx.importedUnitCount(importedCurriculum)).toBe(2)
        expect(ctx.shouldShowTopicAssignmentChip(importedCurriculum.topics[0])).toBe(true)
        expect(ctx.shouldShowTopicAssignmentChip({
            assignment_type: 'none',
            units: [
                {
                    assignment_type: 'weeks',
                    week_keys: ['2026-09-07'],
                },
            ],
        })).toBe(false)
        expect(ctx.shouldShowTopicAssignmentChip({
            assignment_type: 'none',
            units: [
                {
                    assignment_type: 'none',
                },
            ],
        })).toBe(true)
        expect(ctx.formatImportAssignment(importedCurriculum.topics[0])).toBe('Monate: 2026-09, 2026-10')
        expect(ctx.formatImportAssignment(importedCurriculum.topics[0].units[0])).toBe('Sept - KW 37-40')
        expect(ctx.formatImportAssignment(importedCurriculum.topics[1])).toBe('Alle Wochen')
        expect(ctx.formatImportAssignment(importedCurriculum.topics[1].units[0])).toBe('Keine feste Zuweisung')
    })

    it('imports a curriculum through the filepond process callback', async () => {
        const importCurriculum = vi.fn().mockResolvedValue({
            id: 55,
            title: 'Importiert',
        })
        const removeFiles = vi.fn()
        const load = vi.fn()
        const error = vi.fn()
        const progress = vi.fn()
        const abortSpy = vi.fn()
        const ctx = {
            importLoading: false,
            curriculumStore: {
                importCurriculum,
            },
            $refs: {
                importPond: {
                    removeFiles,
                },
            },
            closeImportDialog(force = false) {
                return (CurriculaOverview as any).methods.closeImportDialog.call(this, force)
            },
        }

        const result = (CurriculaOverview as any).methods.importPondProcess.call(
            ctx,
            'file',
            { name: 'curriculum.json' },
            {},
            load,
            error,
            progress,
            abortSpy
        )

        await Promise.resolve()
        await new Promise((resolve) => setTimeout(resolve, 0))

        expect(importCurriculum).toHaveBeenCalledWith({ name: 'curriculum.json' })
        expect(progress).toHaveBeenCalledWith(true, 1, 1)
        expect(load).toHaveBeenCalledWith('55')
        expect(removeFiles).toHaveBeenCalled()
        expect(error).not.toHaveBeenCalled()
        expect(ctx.importLoading).toBe(false)
        expect(result).toEqual({
            abort: expect.any(Function),
        })
    })

    it('deletes an imported curriculum after confirmation', async () => {
        const destroyImportedCurriculum = vi.fn().mockResolvedValue(true)
        const ctx = {
            importedDeleteDialogOpen: true,
            importedDeleteTarget: {
                id: 71,
                title: 'Importiertes Curriculum',
            },
            importedDeleteLoadingId: null,
            curriculumStore: {
                destroyImportedCurriculum,
            },
        }

        await (CurriculaOverview as any).methods.confirmDeleteImportedCurriculum.call(ctx)

        expect(destroyImportedCurriculum).toHaveBeenCalledWith(71)
        expect(ctx.importedDeleteDialogOpen).toBe(false)
        expect(ctx.importedDeleteTarget).toBeNull()
        expect(ctx.importedDeleteLoadingId).toBeNull()
    })

    it('adopts an imported curriculum and opens the created personal curriculum', async () => {
        const adoptImportedCurriculum = vi.fn().mockResolvedValue({
            id: 41,
            title: 'Deutsch importiert',
        })
        const index = vi.fn().mockResolvedValue(true)
        const emit = vi.fn()
        const ctx = {
            takeoverLoadingId: null,
            currentPage: 3,
            curriculumStore: {
                adoptImportedCurriculum,
                index,
            },
            $emit: emit,
        }

        await (CurriculaOverview as any).methods.takeOverImportedCurriculum.call(ctx, {
            id: 41,
            title: 'Deutsch importiert',
        })

        expect(adoptImportedCurriculum).toHaveBeenCalledWith(41)
        expect(index).toHaveBeenCalledWith({
            page: 1,
        })
        expect(ctx.currentPage).toBe(1)
        expect(ctx.takeoverLoadingId).toBeNull()
        expect(emit).toHaveBeenCalledWith('select', {
            id: 41,
            title: 'Deutsch importiert',
        })
    })

    it('renders the print preview toolbar with a right-aligned close button', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/curricula/CurriculaPrint.vue', 'utf8')
        )
        const previewToolbar = source.split('<div class="curricula-print__preview-toolbar d-flex align-center ga-3 mb-4">')[1]
            ?.split('</div>')[0] ?? ''

        expect(previewToolbar).toContain('<v-spacer />')
        expect(previewToolbar).toContain('icon="mdi-close"')
        expect(previewToolbar).toContain('variant="text"')
        expect(previewToolbar).toContain('rounded="lg"')
        expect(previewToolbar).toContain('density="comfortable"')
        expect(previewToolbar).toContain('@click="selectedOption = null" />')
        expect(previewToolbar.indexOf('<v-spacer />')).toBeLessThan(previewToolbar.indexOf('icon="mdi-close"'))
        expect(source).not.toContain('Berichtsauswahl')
        expect(previewToolbar).not.toContain('prepend-icon="mdi-arrow-left"')
    })

    it('shows only the themes overview card in the print chooser', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/curricula/CurriculaPrint.vue', 'utf8')
        )

        expect(source).toContain("key: 'overview'")
        expect(source).toContain("label: 'Themenübersicht'")
        expect(source).not.toContain("key: 'semester'")
        expect(source).not.toContain("label: 'Semesterplan'")
        expect(source).not.toContain("key: 'weekly'")
        expect(source).not.toContain("label: 'Wochenplan'")
        expect(source).toContain('.curricula-print,')
        expect(source).toContain('.curricula-print * {')
        expect(source).toContain('font-family: Arial, Helvetica, sans-serif !important;')
    })

    it('removes the date and month settings card from curricula navigation', async () => {
        const curriculaSource = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/curricula/Curricula.vue', 'utf8')
        )

        expect(curriculaSource).not.toContain("key: 'settings'")
        expect(curriculaSource).not.toContain("import CurriculaSettings from './CurriculaSettings.vue'")
        expect(curriculaSource).not.toContain('<CurriculaSettings')
        expect(curriculaSource).not.toContain('returnFromSettings')
    })

    it('does not render year controls in the curriculum calendar header', async () => {
        const source = await import('node:fs/promises').then((fs) =>
            fs.readFile('resources/js/pages/admin/teaching/curricula/CurriculumDetail.vue', 'utf8')
        )

        expect(source).not.toContain('curriculum-detail__year-picker')
        expect(source).not.toContain('curriculum-detail__month-year')
        expect(source).not.toContain('@click="selectedYear--"')
        expect(source).not.toContain('@click="selectedYear++"')
        expect(source).not.toContain('{{ selectedYear }}/{{ selectedYear + 1 }}')
    })

})
