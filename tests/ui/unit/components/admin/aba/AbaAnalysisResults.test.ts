import { describe, expect, it } from 'vitest'
import AbaAnalysisResults from '@/pages/admin/aba/AbaAnalysisResults.vue'

const methods = (AbaAnalysisResults as any).methods
const computed = (AbaAnalysisResults as any).computed

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
        buildTitlePageRecoveryContext: methods.buildTitlePageRecoveryContext,
        recoverMissingCoreTitle: methods.recoverMissingCoreTitle,
        recoverMissingAuthorFromFinalProperties: methods.recoverMissingAuthorFromFinalProperties,
        recoverMissingClassFromFinalProperties: methods.recoverMissingClassFromFinalProperties,
        recoverMissingDocumentTypeFromFinalProperties: methods.recoverMissingDocumentTypeFromFinalProperties,
        recoverMissingSchoolFromFinalProperties: methods.recoverMissingSchoolFromFinalProperties,
        recoverMissingSchoolFullFromFinalProperties: methods.recoverMissingSchoolFullFromFinalProperties,
        ensureRecoveredDocumentTypeProperty: methods.ensureRecoveredDocumentTypeProperty,
        ensureRecoveredSchoolProperties: methods.ensureRecoveredSchoolProperties,
        recoverSchoolPartsFromNormalizationBlocks: methods.recoverSchoolPartsFromNormalizationBlocks,
        recoverTitleFromNormalizationBlocks: methods.recoverTitleFromNormalizationBlocks,
        isRecoverableTitleCandidate: methods.isRecoverableTitleCandidate,
        titlePageTextLinesFromNormalizationBlocks: methods.titlePageTextLinesFromNormalizationBlocks,
        isTitlePageNormalizationBlock: methods.isTitlePageNormalizationBlock,
        isLikelySchoolNameCandidate: methods.isLikelySchoolNameCandidate,
        isLikelySchoolAddressLine: methods.isLikelySchoolAddressLine,
        isLikelySchoolCityLine: methods.isLikelySchoolCityLine,
        isLikelyTitlePageMetadataLine: methods.isLikelyTitlePageMetadataLine,
        recoverClassToken: methods.recoverClassToken,
        normalizeTitlePageCoreFieldValue: methods.normalizeTitlePageCoreFieldValue,
        normalizeDocumentTypeValue: methods.normalizeDocumentTypeValue,
        cleanLabelArtifactValue: methods.cleanLabelArtifactValue,
        isLocationDatePseudoTitle: methods.isLocationDatePseudoTitle,
        isArchiveNavigationNoise: methods.isArchiveNavigationNoise,
        isPlaceholderMetadataValue: methods.isPlaceholderMetadataValue,
        isMergedMultiFieldValue: methods.isMergedMultiFieldValue,
        normalizeTitlePageAdditionalProperty: methods.normalizeTitlePageAdditionalProperty,
        normalizeTitlePageAdditionalProperties: methods.normalizeTitlePageAdditionalProperties,
        isTitlePageAdditionalPropertyUiMetaText: methods.isTitlePageAdditionalPropertyUiMetaText,
        filterTitlePageAdditionalProperties: methods.filterTitlePageAdditionalProperties,
        buildTitlePageAdditionalPropertyContext: methods.buildTitlePageAdditionalPropertyContext,
        normalizeTitlePagePropertyLabelKey: methods.normalizeTitlePagePropertyLabelKey,
        titlePagePropertyRole: methods.titlePagePropertyRole,
        isCanonicalTitlePagePropertyLabel: methods.isCanonicalTitlePagePropertyLabel,
        comparableTitlePageText: methods.comparableTitlePageText,
        comparableTitlePageContains: methods.comparableTitlePageContains,
        titlePageUppercaseRatio: methods.titlePageUppercaseRatio,
        isNoisyAiImageDescription: methods.isNoisyAiImageDescription,
        isNoisyAiImageDescriptionProperty: methods.isNoisyAiImageDescriptionProperty,
        isSplitArtifactProperty: methods.isSplitArtifactProperty,
        isRedundantAgainstFullSchoolField: methods.isRedundantAgainstFullSchoolField,
        normalizeTitlePageLogoAssets: methods.normalizeTitlePageLogoAssets,
        normalizeTitlePageLogoStatusMessage: methods.normalizeTitlePageLogoStatusMessage,
        titlePageLogoAssetIdentity: methods.titlePageLogoAssetIdentity,
        titlePageLogoSuccessDedupeKey: methods.titlePageLogoSuccessDedupeKey,
        shouldShowTitlePageLogoSummary: methods.shouldShowTitlePageLogoSummary,
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

    it('suppresses pseudo-title lines like "Salzburg, Abgabedatum" while keeping real titles', () => {
        const ctx = createMethodContext()

        const lines = methods.buildTitlePageDetailLinesFromNormalized.call(ctx, {
            title: 'Salzburg, Abgabedatum',
            subtitle: '',
            author: 'Anh Vu Duy',
            advisor: 'Mag. Ungeringer-Kron Gerhild',
            class: '8C',
            date: '2026',
        })
        const linesWithRealTitle = methods.buildTitlePageDetailLinesFromNormalized.call(ctx, {
            title: 'Behandlungsmethoden bei Neurodermitis',
            subtitle: '',
            author: 'Anh Vu Duy',
            advisor: 'Mag. Ungeringer-Kron Gerhild',
            class: '8C',
            date: '2026',
        })

        expect(lines.some((line: string) => line === 'Titel: Salzburg, Abgabedatum')).toBe(false)
        expect(linesWithRealTitle).toContain('Titel: Behandlungsmethoden bei Neurodermitis')
    })

    it('cleans advisor label artifacts like "/in:" in core detail lines', () => {
        const ctx = createMethodContext()

        const lines = methods.buildTitlePageDetailLinesFromNormalized.call(ctx, {
            title: 'Die Bedeutung von monoklonalen Antikörpern',
            subtitle: '',
            author: 'Anna Muster',
            advisor: '/in: Christian Urban, BEd univ. MEd',
            class: '8B',
            date: 'Februar 2024',
        })

        expect(lines).toContain('Betreuer: Christian Urban, BEd univ. MEd')
        expect(lines.some((line: string) => line.includes('/in:'))).toBe(false)
    })

    it('removes placeholder subtitle and punctuation-only author values from core detail lines', () => {
        const ctx = createMethodContext()

        const lines = methods.buildTitlePageDetailLinesFromNormalized.call(ctx, {
            title: 'Leistungsoptimierung im Leistungssport Eishockey durch Schnellkrafttraining',
            subtitle: 'Inhaltsverzeichnis',
            author: ':',
            advisor: 'Christian Urban, BEd univ. MEd',
            class: '9L2',
            date: 'Februar 2026',
        })

        expect(lines.some((line: string) => line.startsWith('Untertitel:'))).toBe(false)
        expect(lines.some((line: string) => line.startsWith('Verfasser*in:'))).toBe(false)
    })

    it('recovers class token from merged multi-field class values in core detail lines', () => {
        const ctx = createMethodContext()

        const lines = methods.buildTitlePageDetailLinesFromNormalized.call(ctx, {
            title: 'Titel',
            subtitle: '',
            author: 'Person',
            advisor: 'Christian Urban, BEd univ. MEd',
            class: '9L2 Schuljahr: 2025/26 Betreuer/in: Christian Urban, BEd univ. MEd',
            date: 'Februar 2026',
        })

        expect(lines).toContain('Klasse: 9L2')
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

    it('removes exact ui meta text "Weitere Eigenschaften" from title page additional properties', () => {
        const ctx = createMethodContext()
        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Weitere Eigenschaften',
                value: 'Weitere Eigenschaften',
                normalized_label: 'weitere_eigenschaften',
                order: 1,
            },
            {
                label: 'Fach',
                value: 'Medieninformatik',
                normalized_label: 'fach',
                order: 2,
            },
        ])

        expect(properties).toEqual([
            {
                label: 'Fach',
                value: 'Medieninformatik',
                source_label: 'Fach',
                normalized_label: 'fach',
                order: 2,
            },
        ])
    })

    it('removes generic ai image description artifacts from final title page additional properties', () => {
        const ctx = createMethodContext()
        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Hinweis',
                value: 'Ein Bild, das Schrift, Grafiken, Text, Kreis enthält. KI: generierte Inhalte können fehlerhaft sein.',
                normalized_label: 'hinweis',
                order: 1,
            },
            {
                label: 'Fach',
                value: 'Medieninformatik',
                normalized_label: 'fach',
                order: 2,
            },
        ])

        expect(properties).toEqual([
            {
                label: 'Fach',
                value: 'Medieninformatik',
                source_label: 'Fach',
                normalized_label: 'fach',
                order: 2,
            },
        ])
    })

    it('removes small variants of ai/noise descriptions when payload is split across label/value', () => {
        const ctx = createMethodContext()
        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Ein Bild, das Schrift, Grafiken, Text, Kreis enthält. KI-',
                value: 'generierte Inhalte koennen fehlerhaft sein.',
                normalized_label: 'hinweis',
                order: 1,
            },
            {
                label: 'Dokumenttyp',
                value: 'DOKU',
                normalized_label: 'dokumenttyp',
                order: 2,
            },
        ])

        expect(properties).toEqual([
            {
                label: 'Dokumenttyp',
                value: 'DOKU',
                source_label: 'Dokumenttyp',
                normalized_label: 'dokumenttyp',
                order: 2,
            },
        ])
    })

    it('removes archive/navigation text from school fields', () => {
        const ctx = createMethodContext()
        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Schule',
                value: 'Archiv] Tag der offenen Tür 2018 – Christian Doppler Gymnasium Salzburg',
                normalized_label: 'schule',
                order: 1,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Archiv] Tag der offenen Tür 2018: Christian Doppler Gymnasium Salzburg',
                normalized_label: 'schule_vollstaendig',
                order: 2,
            },
            {
                label: 'Dokumenttyp',
                value: 'Vorwissenschaftliche Arbeit',
                normalized_label: 'dokumenttyp',
                order: 3,
            },
        ])

        expect(properties).toEqual([
            {
                label: 'Dokumenttyp',
                value: 'Vorwissenschaftliche Arbeit',
                source_label: 'Dokumenttyp',
                normalized_label: 'dokumenttyp',
                order: 3,
            },
        ])
    })

    it('removes archive/navigation text when archive marker is in the property label', () => {
        const ctx = createMethodContext()
        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Archiv] Tag der offenen Tür 2018',
                value: 'Christian Doppler Gymnasium Salzburg',
                normalized_label: 'archiv_tag_der_offenen_tuer_2018',
                order: 1,
            },
            {
                label: 'Dokumenttyp',
                value: 'Vorwissenschaftliche Arbeit',
                normalized_label: 'dokumenttyp',
                order: 2,
            },
        ])

        expect(properties).toEqual([
            {
                label: 'Dokumenttyp',
                value: 'Vorwissenschaftliche Arbeit',
                source_label: 'Dokumenttyp',
                normalized_label: 'dokumenttyp',
                order: 2,
            },
        ])
    })

    it('reduces merged document type values to safe core value when possible', () => {
        const ctx = createMethodContext()
        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Dokumenttyp',
                value: 'Vorwissenschaftliche Arbeit verfasst von',
                normalized_label: 'dokumenttyp',
                order: 1,
            },
        ])

        expect(properties).toEqual([
            {
                label: 'Dokumenttyp',
                value: 'Vorwissenschaftliche Arbeit',
                source_label: 'Dokumenttyp',
                normalized_label: 'dokumenttyp',
                order: 1,
            },
        ])
    })

    it('keeps canonical metadata fields with colon-like label/value structure', () => {
        const ctx = createMethodContext()
        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Dokumenttyp',
                value: 'DOKU',
                normalized_label: 'dokumenttyp',
                order: 1,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Christian Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                normalized_label: 'schule_vollstaendig',
                order: 2,
            },
        ])

        expect(properties).toEqual([
            {
                label: 'Dokumenttyp',
                value: 'DOKU',
                source_label: 'Dokumenttyp',
                normalized_label: 'dokumenttyp',
                order: 1,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Christian Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                source_label: 'Schule (vollständig)',
                normalized_label: 'schule_vollstaendig',
                order: 2,
            },
        ])
    })

    it('removes split artifacts like "Christian Doppler: Gymnasium" when fuller school data exists', () => {
        const ctx = createMethodContext()
        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Schule (vollständig)',
                value: 'Christian Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                normalized_label: 'schule_vollstaendig',
                order: 1,
            },
            {
                label: 'Christian Doppler',
                value: 'Gymnasium',
                normalized_label: 'christian_doppler',
                order: 2,
            },
        ])

        expect(properties).toEqual([
            {
                label: 'Schule (vollständig)',
                value: 'Christian Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                source_label: 'Schule (vollständig)',
                normalized_label: 'schule_vollstaendig',
                order: 1,
            },
        ])
    })

    it('removes split artifacts like "Christian: Doppler-Gymnasium" and "Franz Josef: Kai 41, 5020 Salzburg"', () => {
        const ctx = createMethodContext()
        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Schule (vollständig)',
                value: 'Christian Doppler-Gymnasium, Franz Josef-Kai 41, 5020 Salzburg',
                normalized_label: 'schule_vollstaendig',
                order: 1,
            },
            {
                label: 'Christian',
                value: 'Doppler-Gymnasium',
                normalized_label: 'christian',
                order: 2,
            },
            {
                label: 'Franz Josef',
                value: 'Kai 41, 5020 Salzburg',
                normalized_label: 'franz_josef',
                order: 3,
            },
        ])

        expect(properties).toEqual([
            {
                label: 'Schule (vollständig)',
                value: 'Christian Doppler-Gymnasium, Franz Josef-Kai 41, 5020 Salzburg',
                source_label: 'Schule (vollständig)',
                normalized_label: 'schule_vollstaendig',
                order: 1,
            },
        ])
    })

    it('removes uppercase headline split artifacts like "WER IST BONG JOON: HO? | DOKU"', () => {
        const ctx = createMethodContext()
        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'WER IST BONG JOON',
                value: 'HO? | DOKU',
                normalized_label: 'wer_ist_bong_joon',
                order: 1,
            },
            {
                label: 'Dokumenttyp',
                value: 'DOKU',
                normalized_label: 'dokumenttyp',
                order: 2,
            },
        ])

        expect(properties).toEqual([
            {
                label: 'Dokumenttyp',
                value: 'DOKU',
                source_label: 'Dokumenttyp',
                normalized_label: 'dokumenttyp',
                order: 2,
            },
        ])
    })

    it('removes "Schulort" when fully covered by "Schule (vollständig)"', () => {
        const ctx = createMethodContext()
        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Schule (vollständig)',
                value: 'Christian Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                normalized_label: 'schule_vollstaendig',
                order: 1,
            },
            {
                label: 'Schulort',
                value: '5020 Salzburg',
                normalized_label: 'schulort',
                order: 2,
            },
        ])

        expect(properties).toEqual([
            {
                label: 'Schule (vollständig)',
                value: 'Christian Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                source_label: 'Schule (vollständig)',
                normalized_label: 'schule_vollstaendig',
                order: 1,
            },
        ])
    })

    it('removes "Schuladresse" when it is a contained substring of "Schule (vollständig)"', () => {
        const ctx = createMethodContext()
        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Schuladresse',
                value: 'Franz-Josef-Kai 41',
                normalized_label: 'schuladresse',
                order: 1,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Christian Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                normalized_label: 'schule_vollstaendig',
                order: 2,
            },
        ])

        expect(properties).toEqual([
            {
                label: 'Schule (vollständig)',
                value: 'Christian Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                source_label: 'Schule (vollständig)',
                normalized_label: 'schule_vollstaendig',
                order: 2,
            },
        ])
    })

    it('keeps "Schule" and "Schule (vollständig)" while removing redundant "Schuladresse"', () => {
        const ctx = createMethodContext()
        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Schule',
                value: 'Christian Doppler-Gymnasium',
                normalized_label: 'schule',
                order: 1,
            },
            {
                label: 'Schuladresse',
                value: 'Franz-Josef-Kai 41',
                normalized_label: 'schuladresse',
                order: 2,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Christian Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                normalized_label: 'schule_vollstaendig',
                order: 3,
            },
        ])

        expect(properties).toEqual([
            {
                label: 'Schule',
                value: 'Christian Doppler-Gymnasium',
                source_label: 'Schule',
                normalized_label: 'schule',
                order: 1,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Christian Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                source_label: 'Schule (vollständig)',
                normalized_label: 'schule_vollstaendig',
                order: 3,
            },
        ])
    })

    it('removes "Schuladresse" when it is fully covered by "Schule (vollständig)"', () => {
        const ctx = createMethodContext()
        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Schule (vollständig)',
                value: 'Christian Doppler-Gymnasium, Franz Josef-Kai 41, 5020 Salzburg',
                normalized_label: 'schule_vollstaendig',
                order: 1,
            },
            {
                label: 'Schuladresse',
                value: 'Franz Josef-Kai 41, 5020 Salzburg',
                normalized_label: 'schuladresse',
                order: 2,
            },
        ])

        expect(properties).toEqual([
            {
                label: 'Schule (vollständig)',
                value: 'Christian Doppler-Gymnasium, Franz Josef-Kai 41, 5020 Salzburg',
                source_label: 'Schule (vollständig)',
                normalized_label: 'schule_vollstaendig',
                order: 1,
            },
        ])
    })

    it('keeps aba #3 style canonical fields while filtering split/noise artifacts and redundant address', () => {
        const ctx = createMethodContext()
        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Dokumenttyp',
                value: 'DOKU',
                normalized_label: 'dokumenttyp',
                order: 1,
            },
            {
                label: 'Schule',
                value: 'Christian-Doppler-Gymnasium',
                normalized_label: 'schule',
                order: 2,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Christian-Doppler-Gymnasium, Franz Josef-Kai 41, 5020 Salzburg',
                normalized_label: 'schule_vollstaendig',
                order: 3,
            },
            {
                label: 'Schuladresse',
                value: 'Franz Josef-Kai 41, 5020 Salzburg',
                normalized_label: 'schuladresse',
                order: 4,
            },
            {
                label: 'WER IST BONG JOON',
                value: 'HO? | DOKU',
                normalized_label: 'wer_ist_bong_joon',
                order: 5,
            },
            {
                label: 'Christian',
                value: 'Doppler-Gymnasium',
                normalized_label: 'christian',
                order: 6,
            },
            {
                label: 'Franz Josef',
                value: 'Kai 41, 5020 Salzburg',
                normalized_label: 'franz_josef',
                order: 7,
            },
        ])

        expect(properties).toEqual([
            {
                label: 'Dokumenttyp',
                value: 'DOKU',
                source_label: 'Dokumenttyp',
                normalized_label: 'dokumenttyp',
                order: 1,
            },
            {
                label: 'Schule',
                value: 'Christian-Doppler-Gymnasium',
                source_label: 'Schule',
                normalized_label: 'schule',
                order: 2,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Christian-Doppler-Gymnasium, Franz Josef-Kai 41, 5020 Salzburg',
                source_label: 'Schule (vollständig)',
                normalized_label: 'schule_vollstaendig',
                order: 3,
            },
        ])
    })

    it('keeps aba #6 representative output stable in final core/additional normalization', () => {
        const ctx = createMethodContext()
        const detailLines = methods.buildTitlePageDetailLinesFromNormalized.call(ctx, {
            title: 'Salzburg, Abgabedatum',
            subtitle: '',
            author: 'Anh Vu Duy',
            advisor: '/in: Mag. Ungeringer-Kron Gerhild',
            class: '8C',
            date: '2026',
        }, {
            aba: {
                title: 'Behandlungsmethoden bei Neurodermitis',
                student_name: 'Anh Vu Duy',
            },
        })
        const additional = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Schule',
                value: 'Christian-Doppler-Gymnasium Salzburg',
                normalized_label: 'schule',
                order: 1,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Christian-Doppler-Gymnasium Salzburg, Franz-Josef Kai 41, 5020 Salzburg',
                normalized_label: 'schule_vollstaendig',
                order: 2,
            },
        ])

        expect(detailLines.some((line: string) => line === 'Titel: Salzburg, Abgabedatum')).toBe(false)
        expect(detailLines).toContain('Titel: Behandlungsmethoden bei Neurodermitis')
        expect(detailLines).toContain('Betreuer: Mag. Ungeringer-Kron Gerhild')
        expect(additional).toEqual([
            {
                label: 'Schule',
                value: 'Christian-Doppler-Gymnasium Salzburg',
                source_label: 'Schule',
                normalized_label: 'schule',
                order: 1,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Christian-Doppler-Gymnasium Salzburg, Franz-Josef Kai 41, 5020 Salzburg',
                source_label: 'Schule (vollständig)',
                normalized_label: 'schule_vollstaendig',
                order: 2,
            },
        ])
    })

    it('keeps aba #7 representative output stable in final core/additional normalization', () => {
        const ctx = createMethodContext()
        const detailLines = methods.buildTitlePageDetailLinesFromNormalized.call(ctx, {
            title: 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
            subtitle: '',
            author: '',
            advisor: '/in: Mag. Gerhild Ungeringer-Kron',
            class: '8B',
            date: 'Februar 2024',
        }, {
            aba: {
                student_name: 'Hanna Danninger',
            },
            normalizationBlocks: [
                {
                    order: 1,
                    plain_text: 'Archiv] Tag der offenen Tür 2018 – Christian Doppler Gymnasium Salzburg',
                    document_zone: { zone: 'title_page' },
                },
                {
                    order: 2,
                    plain_text: 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
                    document_zone: { zone: 'title_page' },
                },
                {
                    order: 3,
                    plain_text: 'Vorwissenschaftliche Arbeit verfasst von',
                    document_zone: { zone: 'title_page' },
                },
                {
                    order: 8,
                    plain_text: 'Christian-Doppler-Gymnasium\nFranz-Josef-Kai 41\n5020 Salzburg',
                    document_zone: { zone: 'title_page' },
                },
            ],
        })
        const normalizedAdditional = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Dokumenttyp',
                value: 'Vorwissenschaftliche Arbeit verfasst von',
                normalized_label: 'dokumenttyp',
                order: 1,
            },
            {
                label: 'Schule',
                value: 'Archiv] Tag der offenen Tür 2018 – Christian Doppler Gymnasium Salzburg',
                normalized_label: 'schule',
                order: 2,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Archiv] Tag der offenen Tür 2018: Christian Doppler Gymnasium Salzburg',
                normalized_label: 'schule_vollstaendig',
                order: 3,
            },
        ])
        const withRecoveredDocumentType = methods.ensureRecoveredDocumentTypeProperty.call(ctx, normalizedAdditional, {
            documentTypeLabel: 'aba',
            normalizationBlocks: [
                {
                    order: 1,
                    plain_text: 'Archiv] Tag der offenen Tür 2018 – Christian Doppler Gymnasium Salzburg',
                    document_zone: { zone: 'title_page' },
                },
                {
                    order: 2,
                    plain_text: 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
                    document_zone: { zone: 'title_page' },
                },
                {
                    order: 3,
                    plain_text: 'Vorwissenschaftliche Arbeit verfasst von',
                    document_zone: { zone: 'title_page' },
                },
                {
                    order: 8,
                    plain_text: 'Christian-Doppler-Gymnasium\nFranz-Josef-Kai 41\n5020 Salzburg',
                    document_zone: { zone: 'title_page' },
                },
            ],
        })
        const additional = methods.ensureRecoveredSchoolProperties.call(ctx, withRecoveredDocumentType, {
            normalizationBlocks: [
                {
                    order: 1,
                    plain_text: 'Archiv] Tag der offenen Tür 2018 – Christian Doppler Gymnasium Salzburg',
                    document_zone: { zone: 'title_page' },
                },
                {
                    order: 2,
                    plain_text: 'Die Bedeutung von monoklonalen Antikörpern als Therapeutika in Österreich',
                    document_zone: { zone: 'title_page' },
                },
                {
                    order: 3,
                    plain_text: 'Vorwissenschaftliche Arbeit verfasst von',
                    document_zone: { zone: 'title_page' },
                },
                {
                    order: 8,
                    plain_text: 'Christian-Doppler-Gymnasium\nFranz-Josef-Kai 41\n5020 Salzburg',
                    document_zone: { zone: 'title_page' },
                },
            ],
        })

        expect(detailLines).toContain('Verfasser*in: Hanna Danninger')
        expect(detailLines).toContain('Betreuer: Mag. Gerhild Ungeringer-Kron')
        expect(additional).toEqual([
            {
                label: 'Dokumenttyp',
                value: 'Vorwissenschaftliche Arbeit',
                source_label: 'Dokumenttyp',
                normalized_label: 'dokumenttyp',
                order: 1,
            },
            {
                label: 'Schule',
                value: 'Christian-Doppler-Gymnasium',
                source_label: 'Schule',
                normalized_label: 'schule',
                order: null,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Christian-Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                source_label: 'Schule (vollständig)',
                normalized_label: 'schule_vollstaendig',
                order: null,
            },
        ])
        expect(additional.some((property: any) => String(property?.value || '').includes('Archiv]'))).toBe(false)
    })

    it('keeps aba #8 representative output stable in final core/additional normalization', () => {
        const ctx = createMethodContext()
        const detailLines = methods.buildTitlePageDetailLinesFromNormalized.call(ctx, {
            title: 'Leistungsoptimierung im Leistungssport Eishockey durch Schnellkrafttraining',
            subtitle: 'Inhaltsverzeichnis',
            author: ':',
            advisor: '/in: Christian Urban, BEd univ. MEd',
            class: '9L2 Schuljahr: 2025/26 Betreuer/in: Christian Urban, BEd univ. MEd',
            date: 'Februar 2026',
        }, {
            aba: {
                student_name: 'Fabian Luca Baumann',
            },
            normalizationBlocks: [
                {
                    order: 1,
                    plain_text: 'Salzburg, im Februar 2026\nAbschließende Arbeit',
                    document_zone: { zone: 'title_page' },
                },
                {
                    order: 4,
                    plain_text: 'Fabian Luca Baumann',
                    document_zone: { zone: 'title_page' },
                },
                {
                    order: 5,
                    plain_text: 'Klasse: 9L2\nSchuljahr: 2025/26\nBetreuer/in: Christian Urban, BEd univ. MEd',
                    document_zone: { zone: 'title_page' },
                },
            ],
        })
        const additional = methods.ensureRecoveredDocumentTypeProperty.call(ctx, methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Schule',
                value: 'Christian-Doppler-Gymnasium',
                normalized_label: 'schule',
                order: 1,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Christian-Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                normalized_label: 'schule_vollstaendig',
                order: 2,
            },
        ]), {
            documentTypeLabel: 'aba',
            normalizationBlocks: [
                {
                    order: 1,
                    plain_text: 'Salzburg, im Februar 2026\nAbschließende Arbeit',
                    document_zone: { zone: 'title_page' },
                },
            ],
        })

        expect(detailLines.some((line: string) => line.startsWith('Untertitel:'))).toBe(false)
        expect(detailLines).toContain('Verfasser*in: Fabian Luca Baumann')
        expect(detailLines).toContain('Klasse: 9L2')
        expect(detailLines).toContain('Betreuer: Christian Urban, BEd univ. MEd')
        expect(additional).toEqual([
            {
                label: 'Dokumenttyp',
                value: 'Abschließende Arbeit',
                source_label: 'Dokumenttyp',
                normalized_label: 'dokumenttyp',
                order: null,
            },
            {
                label: 'Schule',
                value: 'Christian-Doppler-Gymnasium',
                source_label: 'Schule',
                normalized_label: 'schule',
                order: 1,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Christian-Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                source_label: 'Schule (vollständig)',
                normalized_label: 'schule_vollstaendig',
                order: 2,
            },
        ])
    })

    it('computes aba #6 detail lines with recovered real title while suppressing pseudo-title', () => {
        const ctx = createMethodContext({
            aba: {
                title: 'Behandlungsmethoden bei Neurodermitis',
                student_name: 'Anh Vu Duy',
            },
            documentReviewNormalizationBlocks: [
                {
                    order: 1,
                    plain_text: 'Christian-Doppler-Gymnasium Salzburg',
                    document_zone: { zone: 'title_page' },
                },
                {
                    order: 4,
                    plain_text: 'Behandlungsmethoden bei Neurodermitis',
                    document_zone: { zone: 'title_page' },
                },
                {
                    order: 8,
                    plain_text: 'Salzburg, Abgabedatum',
                    document_zone: { zone: 'title_page' },
                },
            ],
            documentReviewTitlePageNormalized: {
                title: 'Salzburg, Abgabedatum',
                subtitle: '',
                author: 'Anh Vu Duy',
                advisor: '/in: Mag. Ungeringer-Kron Gerhild',
                class: '8C',
                date: '',
            },
        })

        const detailLines = computed.documentReviewTitlePageNormalizedDetailLines.call(ctx)

        expect(detailLines).toContain('Titel: Behandlungsmethoden bei Neurodermitis')
        expect(detailLines.some((line: string) => line === 'Titel: Salzburg, Abgabedatum')).toBe(false)
    })

    it('computes aba #7 additional properties with recovered school and school_full while filtering archive noise', () => {
        const ctx = createMethodContext({
            documentTypeLabel: 'aba',
            documentReviewNormalizationBlocks: [
                {
                    order: 1,
                    plain_text: 'Archiv] Tag der offenen Tür 2018 – Christian Doppler Gymnasium Salzburg',
                    document_zone: { zone: 'title_page' },
                },
                {
                    order: 3,
                    plain_text: 'Vorwissenschaftliche Arbeit verfasst von',
                    document_zone: { zone: 'title_page' },
                },
                {
                    order: 8,
                    plain_text: 'Christian-Doppler-Gymnasium\nFranz-Josef-Kai 41\n5020 Salzburg',
                    document_zone: { zone: 'title_page' },
                },
            ],
            documentReviewTitlePageNormalized: {
                additional_properties: [
                    {
                        label: 'Dokumenttyp',
                        value: 'Vorwissenschaftliche Arbeit verfasst von',
                        normalized_label: 'dokumenttyp',
                        order: 1,
                    },
                    {
                        label: 'Schule',
                        value: 'Archiv] Tag der offenen Tür 2018 – Christian Doppler Gymnasium Salzburg',
                        normalized_label: 'schule',
                        order: 2,
                    },
                    {
                        label: 'Schule (vollständig)',
                        value: 'Archiv] Tag der offenen Tür 2018: Christian Doppler Gymnasium Salzburg',
                        normalized_label: 'schule_vollstaendig',
                        order: 3,
                    },
                ],
            },
        })

        const properties = computed.documentReviewTitlePageAdditionalProperties.call(ctx)

        expect(properties).toEqual([
            {
                label: 'Dokumenttyp',
                value: 'Vorwissenschaftliche Arbeit',
                source_label: 'Dokumenttyp',
                normalized_label: 'dokumenttyp',
                order: 1,
            },
            {
                label: 'Schule',
                value: 'Christian-Doppler-Gymnasium',
                source_label: 'Schule',
                normalized_label: 'schule',
                order: null,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Christian-Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                source_label: 'Schule (vollständig)',
                normalized_label: 'schule_vollstaendig',
                order: null,
            },
        ])
        expect(properties.some((property: any) => String(property?.value || '').includes('Archiv]'))).toBe(false)
    })

    it('computes aba #8 detail lines and additional properties with recovered author/class/document_type', () => {
        const ctx = createMethodContext({
            aba: {
                student_name: 'Fabian Luca Baumann',
            },
            documentTypeLabel: 'aba',
            documentReviewNormalizationBlocks: [
                {
                    order: 1,
                    plain_text: 'Salzburg, im Februar 2026\nAbschließende Arbeit',
                    document_zone: { zone: 'title_page' },
                },
                {
                    order: 4,
                    plain_text: 'Fabian Luca Baumann',
                    document_zone: { zone: 'title_page' },
                },
                {
                    order: 5,
                    plain_text: 'Klasse: 9L2\nSchuljahr: 2025/26\nBetreuer/in: Christian Urban, BEd univ. MEd',
                    document_zone: { zone: 'title_page' },
                },
            ],
            documentReviewTitlePageNormalized: {
                title: 'Leistungsoptimierung im Leistungssport Eishockey durch Schnellkrafttraining',
                subtitle: 'Inhaltsverzeichnis',
                author: ':',
                advisor: '/in: Christian Urban, BEd univ. MEd',
                class: '9L2 Schuljahr: 2025/26 Betreuer/in: Christian Urban, BEd univ. MEd',
                date: 'Februar 2026',
                additional_properties: [
                    {
                        label: 'Schule',
                        value: 'Christian-Doppler-Gymnasium',
                        normalized_label: 'schule',
                        order: 1,
                    },
                    {
                        label: 'Schule (vollständig)',
                        value: 'Christian-Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                        normalized_label: 'schule_vollstaendig',
                        order: 2,
                    },
                ],
            },
        })

        const detailLines = computed.documentReviewTitlePageNormalizedDetailLines.call(ctx)
        const properties = computed.documentReviewTitlePageAdditionalProperties.call(ctx)

        expect(detailLines).toContain('Verfasser*in: Fabian Luca Baumann')
        expect(detailLines).toContain('Klasse: 9L2')
        expect(properties).toEqual([
            {
                label: 'Dokumenttyp',
                value: 'Abschließende Arbeit',
                source_label: 'Dokumenttyp',
                normalized_label: 'dokumenttyp',
                order: null,
            },
            {
                label: 'Schule',
                value: 'Christian-Doppler-Gymnasium',
                source_label: 'Schule',
                normalized_label: 'schule',
                order: 1,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Christian-Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                source_label: 'Schule (vollständig)',
                normalized_label: 'schule_vollstaendig',
                order: 2,
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

    it('keeps a single successful logo message for one unique logo asset', () => {
        const ctx = createMethodContext()
        const node = {
            id: 'titlepage',
            logo_assets: methods.normalizeTitlePageLogoAssets.call(ctx, [
                {
                    asset_index: 0,
                    logo_detected: true,
                    logo_asset_available: true,
                    logo_ui_displayable: true,
                    logo_asset_path: 'aba/titlepage-assets/logo-1.png',
                    logo_asset_disk: 'local',
                    logo_asset_url: '/api/logo-1',
                    logo_ui_display_note: 'Asset extrahiert und für UI-Rendering verfügbar.',
                    logo_type: 'offizielles Schul-/Institutionslogo',
                },
            ], {}),
            logo_detected: true,
            logo_asset_available: true,
            logo_ui_displayable: true,
            logo_asset_available_count: 1,
            logo_detected_count: 1,
        }

        const summaryVisible = methods.shouldShowTitlePageLogoSummary.call(ctx, node)
        const summaryMessage = summaryVisible ? methods.titlePageLogoStatusText.call(ctx, node) : null
        const assetMessages = node.logo_assets.map((asset: any, index: number) => methods.titlePageLogoAssetStatusText.call(ctx, node, asset, index))
        const successMessageCount = [summaryMessage, ...assetMessages].filter((message) => message === 'Logo-Asset verfügbar und renderbar.').length

        expect(summaryVisible).toBe(false)
        expect(successMessageCount).toBe(1)
    })

    it('deduplicates accidental duplicate success entries for the same logo asset identity', () => {
        const ctx = createMethodContext()

        const assets = methods.normalizeTitlePageLogoAssets.call(ctx, [
            {
                asset_index: 0,
                logo_detected: true,
                logo_asset_available: true,
                logo_ui_displayable: true,
                logo_asset_path: 'aba/titlepage-assets/logo-dup.png',
                logo_asset_disk: 'local',
                logo_asset_url: '/api/logo-dup-a',
                logo_type: 'offizielles Schul-/Institutionslogo',
            },
            {
                asset_index: 1,
                logo_detected: true,
                logo_asset_available: true,
                logo_ui_displayable: true,
                logo_asset_path: 'aba/titlepage-assets/logo-dup.png',
                logo_asset_disk: 'local',
                logo_asset_url: '/api/logo-dup-b',
                logo_type: 'offizielles Schul-/Institutionslogo',
            },
        ], {})

        expect(assets).toHaveLength(1)
        expect(assets[0].logo_asset_path).toBe('aba/titlepage-assets/logo-dup.png')
    })

    it('keeps two different successful logos and does not globally collapse logo entries', () => {
        const ctx = createMethodContext()

        const assets = methods.normalizeTitlePageLogoAssets.call(ctx, [
            {
                asset_index: 0,
                logo_detected: true,
                logo_asset_available: true,
                logo_ui_displayable: true,
                logo_asset_path: 'aba/titlepage-assets/logo-1.png',
                logo_asset_disk: 'local',
                logo_asset_url: '/api/logo-1',
                logo_type: 'offizielles Schul-/Institutionslogo',
            },
            {
                asset_index: 1,
                logo_detected: true,
                logo_asset_available: true,
                logo_ui_displayable: true,
                logo_asset_path: 'aba/titlepage-assets/logo-2.png',
                logo_asset_disk: 'local',
                logo_asset_url: '/api/logo-2',
                logo_type: 'Wappen / Emblem',
            },
        ], {})

        expect(assets).toHaveLength(2)
        expect(assets.map((asset: any) => asset.logo_asset_path)).toEqual([
            'aba/titlepage-assets/logo-1.png',
            'aba/titlepage-assets/logo-2.png',
        ])
    })

    it('keeps other text and image entries while only removing exact ui meta text and duplicate same-asset success logos', () => {
        const ctx = createMethodContext()

        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Weitere Eigenschaften',
                value: 'Weitere Eigenschaften',
                normalized_label: 'weitere_eigenschaften',
                order: 1,
            },
            {
                label: 'Hinweis',
                value: 'Institutionelles Partnerlogo links oben',
                normalized_label: 'hinweis',
                order: 2,
            },
        ])

        const assets = methods.normalizeTitlePageLogoAssets.call(ctx, [
            {
                asset_index: 0,
                logo_detected: true,
                logo_asset_available: true,
                logo_ui_displayable: true,
                logo_asset_path: 'aba/titlepage-assets/logo-main.png',
                logo_asset_disk: 'local',
                logo_asset_url: '/api/logo-main',
                logo_type: 'offizielles Schul-/Institutionslogo',
            },
            {
                asset_index: 1,
                logo_detected: true,
                logo_asset_available: true,
                logo_ui_displayable: true,
                logo_asset_path: 'aba/titlepage-assets/logo-main.png',
                logo_asset_disk: 'local',
                logo_asset_url: '/api/logo-main-duplicate',
                logo_type: 'offizielles Schul-/Institutionslogo',
            },
            {
                asset_index: 2,
                logo_detected: true,
                logo_asset_available: false,
                logo_ui_displayable: false,
                logo_asset_path: null,
                logo_asset_url: null,
                logo_description: 'Ein Bild, das Schrift, Grafiken, Text, Kreis enthält...',
                logo_type: 'sonstiges Bildelement',
            },
        ], {})

        expect(properties).toEqual([
            {
                label: 'Hinweis',
                value: 'Institutionelles Partnerlogo links oben',
                source_label: 'Hinweis',
                normalized_label: 'hinweis',
                order: 2,
            },
        ])
        expect(assets).toHaveLength(2)
        expect(assets.filter((asset: any) => asset.logo_asset_available)).toHaveLength(1)
        expect(assets.filter((asset: any) => !asset.logo_asset_available)).toHaveLength(1)
    })

    it('does not regress when no logos are present', () => {
        const ctx = createMethodContext()
        const assets = methods.normalizeTitlePageLogoAssets.call(ctx, [], {})
        const summaryVisible = methods.shouldShowTitlePageLogoSummary.call(ctx, {
            id: 'titlepage',
            logo_assets: assets,
            logo_detected: false,
            logo_asset_available: false,
            logo_ui_displayable: false,
            logo_asset_url: null,
        })

        expect(assets).toEqual([])
        expect(summaryVisible).toBe(true)
    })

    it('does not remove clean title-page properties when no artifact heuristics match', () => {
        const ctx = createMethodContext()
        const properties = methods.normalizeTitlePageAdditionalProperties.call(ctx, [
            {
                label: 'Dokumenttyp',
                value: 'DOKU',
                normalized_label: 'dokumenttyp',
                order: 1,
            },
            {
                label: 'Schule',
                value: 'Christian Doppler-Gymnasium',
                normalized_label: 'schule',
                order: 2,
            },
            {
                label: 'Schuladresse',
                value: 'Alpenstraße 12',
                normalized_label: 'schuladresse',
                order: 3,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Christian Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                normalized_label: 'schule_vollstaendig',
                order: 4,
            },
            {
                label: 'Hinweis',
                value: 'Kooperation mit externem Partner.',
                normalized_label: 'hinweis',
                order: 5,
            },
        ])

        expect(properties).toEqual([
            {
                label: 'Dokumenttyp',
                value: 'DOKU',
                source_label: 'Dokumenttyp',
                normalized_label: 'dokumenttyp',
                order: 1,
            },
            {
                label: 'Schule',
                value: 'Christian Doppler-Gymnasium',
                source_label: 'Schule',
                normalized_label: 'schule',
                order: 2,
            },
            {
                label: 'Schuladresse',
                value: 'Alpenstraße 12',
                source_label: 'Schuladresse',
                normalized_label: 'schuladresse',
                order: 3,
            },
            {
                label: 'Schule (vollständig)',
                value: 'Christian Doppler-Gymnasium, Franz-Josef-Kai 41, 5020 Salzburg',
                source_label: 'Schule (vollständig)',
                normalized_label: 'schule_vollstaendig',
                order: 4,
            },
            {
                label: 'Hinweis',
                value: 'Kooperation mit externem Partner.',
                source_label: 'Hinweis',
                normalized_label: 'hinweis',
                order: 5,
            },
        ])
    })
})
