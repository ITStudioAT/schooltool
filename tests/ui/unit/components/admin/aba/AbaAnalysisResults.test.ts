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
})
