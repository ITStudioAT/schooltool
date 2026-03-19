import { describe, expect, it } from 'vitest'
import AbaAnalysisResults from '@/pages/admin/aba/AbaAnalysisResults.vue'

const methods = (AbaAnalysisResults as any).methods

function createMethodContext(overrides: Record<string, unknown> = {}) {
    return {
        normalizeProjectionLine: methods.normalizeProjectionLine,
        normalizeProjectionLines: methods.normalizeProjectionLines,
        isPandocTocNode: methods.isPandocTocNode,
        extractSectionNumbering: methods.extractSectionNumbering,
        numberingDepth: methods.numberingDepth,
        sectionCompareKey: methods.sectionCompareKey,
        isNumericSectionNumbering: methods.isNumericSectionNumbering,
        pandocSectionTextWithNumbering: methods.pandocSectionTextWithNumbering,
        pandocMainSectionNumberingLookup: methods.pandocMainSectionNumberingLookup,
        pandocResolvedSectionNumbering: methods.pandocResolvedSectionNumbering,
        pandocTocSectionNumberingLookup: methods.pandocTocSectionNumberingLookup,
        pandocNormalizeTocEntryText: methods.pandocNormalizeTocEntryText,
        shouldRenderAsSubheadingCandidate: methods.shouldRenderAsSubheadingCandidate,
        shouldMergePandocFlowLines: methods.shouldMergePandocFlowLines,
        mergePandocFlowLines: methods.mergePandocFlowLines,
        pandocNodeIsMainSection: methods.pandocNodeIsMainSection,
        pandocNodeDirectContentText: methods.pandocNodeDirectContentText,
        pandocNodeContentText: methods.pandocNodeContentText,
        pandocNodeContentWithChildrenText: methods.pandocNodeContentWithChildrenText,
        pandocNodeRenderedContent: methods.pandocNodeRenderedContent,
        pandocContentTextLines: methods.pandocContentTextLines,
        buildTitlePageDetailLinesFromNormalized: methods.buildTitlePageDetailLinesFromNormalized,
        normalizeTitlePageAdditionalProperties: methods.normalizeTitlePageAdditionalProperties,
        normalizeTitlePageLogoAssets: methods.normalizeTitlePageLogoAssets,
        resolveTitlePageLogoStatus: methods.resolveTitlePageLogoStatus,
        resolveTitlePageLogoAssetStatus: methods.resolveTitlePageLogoAssetStatus,
        titlePageLogoNodeKey: methods.titlePageLogoNodeKey,
        titlePageLogoAssetNodeKey: methods.titlePageLogoAssetNodeKey,
        titlePageLogoRenderErrors: {},
        titlePageLogoRenderLoads: {},
        ...overrides,
    }
}

describe('AbaAnalysisResults pandoc content projection', () => {
    it('uses full direct section text for main sections with children', () => {
        const ctx = createMethodContext()
        const node = {
            semantic_type: 'chapter',
            type: 'chapter',
            children: [{ id: 'h2-1' }],
            content_direct_text: 'Einleitung Absatz 1\nDas zweite Kapitel widmet sich ...\nIch arbeite in meiner ABA hermeneutisch ...',
            content_direct_preview_lines: ['Einleitung Absatz 1'],
            content_with_children_preview_lines: ['Einleitung Absatz 1', 'Das zweite Kapitel widmet sich ...'],
            content_preview_lines: ['Einleitung Absatz 1'],
        }

        const lines = methods.pandocNodeContentLines.call(ctx, node, 20)

        expect(lines).toEqual([
            'Einleitung Absatz 1',
            'Das zweite Kapitel widmet sich ...',
            'Ich arbeite in meiner ABA hermeneutisch ...',
        ])
    })

    it('keeps toc projection lines as primary source for toc nodes', () => {
        const ctx = createMethodContext()
        const node = {
            semantic_type: 'toc',
            type: 'table_of_contents',
            content_preview_lines: [
                '1 Einleitung 7',
                '  1.1 Klassische Medien 8',
            ],
            content_text: 'Rohtext der nicht priorisiert werden soll',
        }

        const lines = methods.pandocNodeContentLines.call(ctx, node, 20)

        expect(lines).toEqual([
            '1 Einleitung 7',
            '  1.1 Klassische Medien 8',
        ])
    })

    it('derives larger display limits for main section nodes', () => {
        const ctx = createMethodContext()

        const chapterLimit = methods.pandocNodeDisplayLineLimit.call(ctx, {
            semantic_type: 'chapter',
            type: 'chapter',
            content_direct_line_count: 29,
        }, { descendant: true })
        const tocLimit = methods.pandocNodeDisplayLineLimit.call(ctx, {
            semantic_type: 'toc',
            type: 'table_of_contents',
        })

        expect(chapterLimit).toBeGreaterThanOrEqual(29)
        expect(tocLimit).toBe(180)
    })

    it('restores missing numbering for toc entries when chapter numbering exists', () => {
        const ctx = createMethodContext({
            pandocMainSectionNumberingLookup: () => ({
                [methods.sectionCompareKey.call({}, 'Entwicklung der Fotografie im Kontext sozialer Medien')]: '2',
            }),
        })

        const normalized = methods.normalizePandocContentLine.call(ctx, 'Entwicklung der Fotografie im Kontext sozialer Medien 9', { tocMode: true })

        expect(normalized?.text).toBe('2. Entwicklung der Fotografie im Kontext sozialer Medien')
        expect(normalized?.page).toBe('9')
        expect(normalized?.kind).toBe('toc-entry')
    })

    it('keeps flowing prose as paragraph and merges split sentence lines', () => {
        const ctx = createMethodContext()
        const mergedLines = methods.pandocContentTextLines.call(
            ctx,
            'Abschließend fasse ich die wichtigsten Ergebnisse in Kapitel\n8 zusammen.\nDie Angaben zu den vollständigen Quellen stehen im\nLiteraturverzeichnis.'
        )
        const normalized = methods.normalizePandocContentLine.call(
            ctx,
            'Abschließend fasse ich die wichtigsten Ergebnisse in Kapitel 8 zusammen.',
            { tocMode: false }
        )

        expect(mergedLines).toEqual([
            'Abschließend fasse ich die wichtigsten Ergebnisse in Kapitel 8 zusammen.',
            'Die Angaben zu den vollständigen Quellen stehen im Literaturverzeichnis.',
        ])
        expect(normalized?.kind).toBe('paragraph')
    })

    it('builds title page detail lines from normalized output without redundant school year', () => {
        const ctx = createMethodContext()

        const lines = methods.buildTitlePageDetailLinesFromNormalized.call(ctx, {
            title: 'Die Rolle der Fotografie in sozialen Medien',
            subtitle: 'Ästhetik, Technologie und gesellschaftliche Auswirkungen',
            author: 'Sandra Banu',
            advisor: 'Dipl.-Ing. Günther Kron',
            class: '8M',
            date: '2025/26',
            school_year: '2025/26',
        })

        expect(lines).toContain('Datum: 2025/26')
        expect(lines.some((line: string) => line.startsWith('Schuljahr:'))).toBe(false)
    })

    it('distinguishes title page logo status for detected, missing asset and render failure', () => {
        const ctxNoLogo = createMethodContext()
        const noLogoStatus = methods.resolveTitlePageLogoStatus.call(ctxNoLogo, {
            id: 'titlepage',
            logo_detected: false,
            logo_asset_available: false,
            logo_ui_displayable: false,
            logo_asset_url: null,
        })

        const ctxNoAsset = createMethodContext()
        const noAssetStatus = methods.resolveTitlePageLogoStatus.call(ctxNoAsset, {
            id: 'titlepage',
            logo_detected: true,
            logo_asset_available: false,
            logo_ui_displayable: false,
            logo_asset_url: null,
        })

        const ctxRenderFail = createMethodContext({
            titlePageLogoRenderErrors: { titlepage: true },
        })
        const renderFailStatus = methods.resolveTitlePageLogoStatus.call(ctxRenderFail, {
            id: 'titlepage',
            logo_detected: true,
            logo_asset_available: true,
            logo_ui_displayable: true,
            logo_asset_url: '/api/admin/abas/1/analysis/document-review/logo-asset?path=aba%2Ftitlepage-assets%2Flogo.png',
        })

        expect(noLogoStatus).toBe('no_logo_detected')
        expect(noAssetStatus).toBe('detected_without_asset')
        expect(renderFailStatus).toBe('asset_render_failed')
    })

    it('normalizes multiple title page logo assets and preserves per-asset ui availability', () => {
        const ctx = createMethodContext()

        const assets = methods.normalizeTitlePageLogoAssets.call(ctx, [
            {
                asset_index: 0,
                logo_detected: true,
                logo_asset_available: true,
                logo_ui_displayable: true,
                logo_asset_path: 'aba/titlepage-assets/logo-1.png',
                logo_asset_url: '/api/logo-1',
                logo_alt_text: 'Schullogo',
            },
            {
                asset_index: 1,
                logo_detected: true,
                logo_asset_available: false,
                logo_ui_displayable: false,
                logo_asset_path: null,
                logo_asset_url: null,
                logo_alt_text: 'Partnerlogo',
            },
        ], {
            logo_assets: [
                {
                    asset_index: 0,
                    logo_asset_url: '/api/logo-1',
                },
            ],
        })

        expect(assets).toHaveLength(2)
        expect(assets[0].logo_ui_displayable).toBe(true)
        expect(assets[0].logo_asset_url).toBe('/api/logo-1')
        expect(assets[1].logo_ui_displayable).toBe(false)
        expect(assets[1].logo_asset_url).toBeNull()
    })

    it('normalizes additional title page properties in stable order and removes duplicates', () => {
        const ctx = createMethodContext()
        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Version',
                value: '1.0',
                normalized_label: 'version',
                order: 8,
            },
            {
                label: 'Fach',
                value: 'Medieninformatik',
                normalized_label: 'fach',
                order: 4,
            },
            {
                label: 'Fach',
                value: 'Medieninformatik',
                normalized_label: 'fach',
                order: 5,
            },
            {
                label: 'Ort',
                value: 'Wien',
                normalized_label: 'ort',
                order: 6,
            },
        ])

        expect(properties).toEqual([
            {
                label: 'Fach',
                value: 'Medieninformatik',
                source_label: 'Fach',
                normalized_label: 'fach',
                order: 4,
            },
            {
                label: 'Ort',
                value: 'Wien',
                source_label: 'Ort',
                normalized_label: 'ort',
                order: 6,
            },
            {
                label: 'Version',
                value: '1.0',
                source_label: 'Version',
                normalized_label: 'version',
                order: 8,
            },
        ])
    })

    it('returns warning status for mixed multi-logo title pages with missing assets', () => {
        const ctx = createMethodContext()
        const status = methods.resolveTitlePageLogoStatus.call(ctx, {
            id: 'titlepage',
            logo_assets: [
                {
                    asset_index: 0,
                    logo_detected: true,
                    logo_asset_available: true,
                    logo_ui_displayable: true,
                    logo_asset_url: '/api/logo-1',
                },
                {
                    asset_index: 1,
                    logo_detected: true,
                    logo_asset_available: false,
                    logo_ui_displayable: false,
                    logo_asset_url: null,
                },
            ],
            logo_detected_count: 2,
            logo_asset_available_count: 1,
        })

        expect(status).toBe('detected_without_asset')
    })
})
