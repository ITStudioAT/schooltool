import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import CourseDates from '@/pages/admin/teaching/overview/components/CourseDates.vue'

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
    },
}))

describe('CourseDates course-specific schema', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('prefers the selected course schema snapshot for semester count', () => {
        const computed = (CourseDates as any).computed
        const ctx: Record<string, unknown> = {
            selected_course: {
                teaching_schema_id: 'schema-teacher',
                teacher_teaching_schema: {
                    id: 'schema-teacher',
                    grading: { semester_count: 2 },
                },
            },
            teachingStore: {
                schemaById: () => ({ id: 'schema-global', grading: { semester_count: 1 } }),
            },
        }

        ctx.selectedCourseSchema = computed.selectedCourseSchema.call(ctx)

        expect((ctx.selectedCourseSchema as any).id).toBe('schema-teacher')
        expect(computed.semesterCount.call(ctx)).toBe(2)
    })

    it('shows the assigned curriculum in the dates card header', () => {
        const computed = (CourseDates as any).computed
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')
        const ctx: Record<string, unknown> = {
            selected_course: {
                teaching_curriculum: {
                    id: 10,
                    title: 'Deutsch 6',
                },
            },
        }

        expect(computed.selectedCourseCurriculumTitle.call(ctx)).toBe('Deutsch 6')
        expect(source).toContain('v-if="selectedCourseCurriculumTitle"')
        expect(source).toContain('class="course-date-curriculum-chip"')
        expect(source).toContain('class="course-date-curriculum-inline pl-1 pr-2"')
        expect(source).toContain('class="course-date-curriculum-stack"')
        expect(source).toContain('class="course-date-curriculum-stack__content"')
        expect(source).toContain('class="course-date-curriculum-stack__entry d-flex align-center"')
        expect(source).toContain('courseDateInlineContent(courseDate)')
        expect(source).toContain(':key="`${courseDate.id}-inline-${entryIndex}`"')
        expect(source).toContain('await this.loadSelectedCourseCurriculumDetail()')
        expect(source).not.toContain('mdi-eye-off-outline')
        expect(source).not.toContain('show_dates = false')
    })

    it('shows a left aligned semester selector beside the date range selector', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/overview/components/CourseDates.vue'), 'utf8')

        expect(source).toContain('class="course-date-semester-selection d-flex justify-start"')
        expect(source).toContain('<v-btn :value="1" size="small">1. Sem</v-btn>')
        expect(source).toContain('<v-btn :value="2" size="small">2. Sem</v-btn>')
        expect(source).toContain('<v-btn :value="3" size="small">Sem 1+2</v-btn>')
        expect(source.indexOf('course-date-semester-selection')).toBeLessThan(source.indexOf('dateRangeSelection'))
        expect(source).not.toContain('!compactStudentView && semesterCount === 2')
    })

    it('does not show a curriculum label when none is assigned', () => {
        const computed = (CourseDates as any).computed
        const ctx: Record<string, unknown> = {
            selected_course: {
                teaching_curriculum: null,
            },
        }

        expect(computed.selectedCourseCurriculumTitle.call(ctx)).toBe('')
    })

    it('keeps the visible dates anchored to the highlighted date when selecting another date', () => {
        const computed = (CourseDates as any).computed
        const dates = [
            { id: 1, date: '2026-01-01' },
            { id: 2, date: '2026-01-08' },
            { id: 3, date: '2026-01-15' },
            { id: 4, date: '2026-01-22' },
            { id: 5, date: '2026-01-29' },
            { id: 6, date: '2026-02-05' },
            { id: 7, date: '2026-02-12' },
        ]
        const ctx: Record<string, unknown> = {
            filteredCourseDates: dates,
            selected_courseDate: { id: 6, date: '2026-02-05' },
            highlightedDateId: 4,
            dateRangeSelection: ['today'],
            compactStudentView: false,
        }

        expect(computed.displayedCourseDates.call(ctx).map((courseDate: Record<string, unknown>) => courseDate.id)).toEqual([2, 3, 4, 5, 6])
    })

    it('falls back to the selected date as visible anchor when no highlighted date exists', () => {
        const computed = (CourseDates as any).computed
        const dates = [
            { id: 1, date: '2026-01-01' },
            { id: 2, date: '2026-01-08' },
            { id: 3, date: '2026-01-15' },
            { id: 4, date: '2026-01-22' },
            { id: 5, date: '2026-01-29' },
            { id: 6, date: '2026-02-05' },
            { id: 7, date: '2026-02-12' },
        ]
        const ctx: Record<string, unknown> = {
            filteredCourseDates: dates,
            selected_courseDate: { id: 4, date: '2026-01-22' },
            highlightedDateId: null,
            dateRangeSelection: ['today'],
            compactStudentView: false,
        }

        expect(computed.displayedCourseDates.call(ctx).map((courseDate: Record<string, unknown>) => courseDate.id)).toEqual([2, 3, 4, 5, 6])
    })

    it('resolves matching curriculum entries for a course date and prioritizes free weeks', () => {
        const methods = (CourseDates as any).methods
        const ctx: Record<string, any> = {
            config: {
                selected_schoolyear: {
                    from: '2025-09-01',
                    until: '2025-09-30',
                },
            },
            selectedCourseCurriculumForContent: {
                free_weeks: ['2025-09-15'],
                topics: [
                    {
                        id: 'topic-all-weeks',
                        title: 'Schreibuebungen',
                        assignment_type: 'all_weeks',
                        week_keys: [],
                        units: [],
                    },
                    {
                        id: 'topic-month',
                        title: 'Monatsprojekt',
                        assignment_type: 'month',
                        month_key: '2025-09',
                        units: [],
                    },
                    {
                        id: 'topic-search',
                        title: 'Suchmaschinen und Internetrecherche - Teil 1',
                        assignment_type: 'none',
                        week_keys: [],
                        units: [
                            {
                                id: 'unit-project',
                                title: 'Projekt: Internetrecherche',
                                assignment_type: 'weeks',
                                week_keys: ['2025-09-08'],
                            },
                        ],
                    },
                ],
            },
        }

        Object.assign(ctx, methods)

        expect(methods.curriculumEntriesForCourseDate.call(ctx, { date: '2025-09-08' }).map((entry: Record<string, any>) => entry.label)).toEqual([
            'Schreibuebungen',
            'Monatsprojekt',
            'Suchmaschinen und Internetrecherche - Teil 1: Projekt: Internetrecherche',
        ])
        expect(methods.curriculumEntriesForCourseDate.call(ctx, { date: '2025-09-15' }).map((entry: Record<string, any>) => entry.label)).toEqual(['Frei'])
    })

    it('normalizes course date content to a single inline line', () => {
        const methods = (CourseDates as any).methods

        expect(methods.courseDateInlineContent.call({}, {
            content: '<p>Algorithmen, Flussdiagramm</p><p>Schleifen, Zählen bis 5.</p>',
        })).toBe('Algorithmen, Flussdiagramm Schleifen, Zählen bis 5.')

        expect(methods.courseDateInlineContent.call({}, { content: '   ' })).toBe('')
    })

    it('enters saving mode before waiting for the date content update request', async () => {
        const methods = (CourseDates as any).methods
        let continueNextTick: (() => void) | null = null
        let resolveUpdate: ((value: unknown) => void) | null = null

        const update = vi.fn(() => new Promise((resolve) => {
            resolveUpdate = resolve
        }))
        const index = vi.fn().mockResolvedValue(true)

        const ctx: Record<string, any> = {
            action: 'edit_course_date_content',
            editing_content_id: 7,
            saving_content_id: null,
            content_drafts: { 7: '<p>Neuer Inhalt</p>' },
            courseDateStore: { update },
            courseStore: { index },
            show_contents: true,
            collapsed_content_ids: [7],
            expanded_content_ids: [],
            isSavingContent: false,
            $nextTick: vi.fn(() => new Promise<void>((resolve) => {
                continueNextTick = resolve
            })),
        }

        const savePromise = methods.saveContent.call(ctx, {
            id: 7,
            date: '2026-04-23',
        })

        expect(ctx.saving_content_id).toBe(7)
        expect(ctx.$nextTick).toHaveBeenCalledTimes(1)
        expect(update).not.toHaveBeenCalled()

        continueNextTick?.()
        await Promise.resolve()

        expect(update).toHaveBeenCalledWith({
            id: 7,
            date: '2026-04-23',
            content: '<p>Neuer Inhalt</p>',
        })

        resolveUpdate?.(true)
        await savePromise

        expect(index).toHaveBeenCalledTimes(1)
        expect(ctx.editing_content_id).toBeNull()
        expect(ctx.action).toBe('')
        expect(ctx.saving_content_id).toBeNull()
        expect(ctx.collapsed_content_ids).toEqual([])
    })

    it('selects all file attachments by default when loading adoptable materials', async () => {
        const methods = (CourseDates as any).methods
        vi.mocked(axios.get).mockResolvedValueOnce({
            data: {
                data: {
                    id: 10,
                    attachments: [
                        { id: 101, attachment_type: 'file', name: 'Arbeitsblatt.pdf' },
                        { id: 102, attachment_type: 'link', name: 'Quelle' },
                    ],
                },
            },
        })

        const ctx: Record<string, any> = {
            selectedCourseCurriculumId: 7,
            adoptDialogCourseDate: { adopted_materials: [] },
            adoptDialogEntry: {
                materials: [{ id: 10, title: 'Arbeitsblatt' }],
            },
            adoptDialogMaterialCards: [],
            adoptDialogMaterialLoading: false,
            adoptDialogSelectedAttachmentIdsByMaterial: {},
        }
        Object.assign(ctx, methods)

        await methods.loadAdoptDialogMaterialCards.call(ctx)

        expect(axios.get).toHaveBeenCalledWith('/api/admin/teaching/curricula/7/materials/cards/10')
        expect(ctx.adoptDialogSelectedAttachmentIdsByMaterial[10]).toEqual([101])
        expect(ctx.adoptDialogMaterialCards).toHaveLength(1)
        expect(ctx.adoptDialogMaterialLoading).toBe(false)
    })

    it('does not mark a curriculum entry as fully adopted until all attachments are adopted', () => {
        const methods = (CourseDates as any).methods
        const ctx: Record<string, any> = {
            adoptDialogMaterialCards: [],
        }
        Object.assign(ctx, methods)

        const courseDate = {
            adopted_materials: [
                {
                    title: 'Quellenarbeit',
                    source_material_card_id: 10,
                    attachments: [
                        { source_material_card_attachment_id: 101 },
                    ],
                },
            ],
        }
        const entry = {
            label: 'Quellenarbeit',
            materials: [
                { id: 10, title: 'Material', attachments_count: 2 },
            ],
        }

        expect(methods.isCurriculumEntryFullyAdopted.call(ctx, courseDate, entry)).toBe(false)

        courseDate.adopted_materials[0].attachments.push({ source_material_card_attachment_id: 102 })

        expect(methods.isCurriculumEntryFullyAdopted.call(ctx, courseDate, entry)).toBe(true)
    })

    it('groups adopted curriculum materials under one curriculum title', () => {
        const methods = (CourseDates as any).methods
        const ctx: Record<string, any> = {}
        Object.assign(ctx, methods)

        const groups = methods.courseDateAdoptedMaterialGroups.call(ctx, {
            adopted_materials: [
                {
                    id: 1,
                    title: 'Suchmaschinen und Internetrecherche - Teil 1: Projekt: Internetrecherche',
                    material_title: 'Word - Einführung',
                    source_material_card_id: 10,
                    type: 'Arbeitsblatt',
                    attachments: [{ id: 101, name: 'Word.pdf' }],
                },
                {
                    id: 2,
                    title: 'Suchmaschinen und Internetrecherche - Teil 1: Projekt: Internetrecherche',
                    material_title: 'Word - Einführung',
                    source_material_card_id: 10,
                    type: 'Arbeitsblatt',
                    attachments: [{ id: 102, name: 'Übung.pdf' }],
                },
                {
                    id: 3,
                    title: 'Suchmaschinen und Internetrecherche - Teil 1: Projekt: Internetrecherche',
                    material_title: 'Browser Grundlagen',
                    source_material_card_id: 11,
                    type: 'Link',
                    attachments: [{ id: 103, name: 'Recherche.pdf' }],
                },
            ],
        })

        expect(groups).toHaveLength(1)
        expect(groups[0].title).toBe('Suchmaschinen und Internetrecherche - Teil 1: Projekt: Internetrecherche')
        expect(groups[0].materials).toHaveLength(2)
        expect(groups[0].materials[0].title).toBe('Word - Einführung')
        expect(groups[0].materials[0].attachments.map((attachment: Record<string, any>) => attachment.name)).toEqual(['Word.pdf', 'Übung.pdf'])
        expect(groups[0].materials[1].title).toBe('Browser Grundlagen')
    })

    it('marks duplicate single adopted material so only the checked curriculum title is rendered', () => {
        const methods = (CourseDates as any).methods
        const ctx: Record<string, any> = {}
        Object.assign(ctx, methods)

        const groups = methods.courseDateAdoptedMaterialGroups.call(ctx, {
            adopted_materials: [
                {
                    id: 1,
                    title: 'Schreibübungen',
                    material_title: 'Schreibübungen',
                    source_material_card_id: 10,
                    type: 'Arbeitsblatt',
                    attachments: [],
                },
            ],
        })

        expect(groups).toHaveLength(1)
        expect(groups[0].title).toBe('Schreibübungen')
        expect(groups[0].duplicateSingleMaterial).toBe(true)
        expect(groups[0].materials[0].title).toBe('Schreibübungen')
    })

    it('selects only missing attachments when reopening a partially adopted material', async () => {
        const methods = (CourseDates as any).methods
        vi.mocked(axios.get).mockResolvedValueOnce({
            data: {
                data: {
                    id: 10,
                    attachments: [
                        { id: 101, attachment_type: 'file', name: 'Bereits übernommen.pdf' },
                        { id: 102, attachment_type: 'file', name: 'Noch offen.pdf' },
                    ],
                },
            },
        })

        const ctx: Record<string, any> = {
            selectedCourseCurriculumId: 7,
            adoptDialogCourseDate: {
                adopted_materials: [
                    {
                        source_material_card_id: 10,
                        attachments: [
                            { source_material_card_attachment_id: 101 },
                        ],
                    },
                ],
            },
            adoptDialogEntry: {
                materials: [{ id: 10, title: 'Arbeitsblatt', attachments_count: 2 }],
            },
            adoptDialogMaterialCards: [],
            adoptDialogMaterialLoading: false,
            adoptDialogSelectedAttachmentIdsByMaterial: {},
            adoptDialogSelectedMaterialIds: [],
        }
        Object.assign(ctx, methods)

        await methods.loadAdoptDialogMaterialCards.call(ctx)

        expect(ctx.adoptDialogSelectedMaterialIds).toEqual([10])
        expect(ctx.adoptDialogSelectedAttachmentIdsByMaterial[10]).toEqual([102])
        expect(methods.isAdoptDialogMaterialFullyAdopted.call(ctx, { id: 10 })).toBe(false)
    })

    it('sends selected attachment ids when adopting curriculum material', async () => {
        const methods = (CourseDates as any).methods
        vi.mocked(axios.post).mockResolvedValueOnce({ data: {} })

        const ctx: Record<string, any> = {
            adoptDialogCourseDate: { id: 15 },
            adoptDialogEntry: {},
            adoptDialogText: 'Quellenarbeit',
            adoptDialogSelectedMaterialIds: [10, 11],
            adoptDialogSelectedAttachmentIdsByMaterial: {
                10: [101],
                11: [],
            },
            adoptSaving: false,
            courseStore: {
                index: vi.fn().mockResolvedValue(true),
            },
            closeAdoptDialog: vi.fn(),
        }

        await methods.confirmAdopt.call(ctx)

        expect(axios.post).toHaveBeenCalledWith('/api/admin/teaching/course_dates/15/adopt-curriculum-content', {
            content: 'Quellenarbeit',
            material_card_ids: [10, 11],
            material_attachment_ids: {
                10: [101],
                11: [],
            },
        })
        expect(ctx.courseStore.index).toHaveBeenCalledTimes(1)
        expect(ctx.closeAdoptDialog).toHaveBeenCalledTimes(1)
        expect(ctx.adoptSaving).toBe(false)
    })
})
