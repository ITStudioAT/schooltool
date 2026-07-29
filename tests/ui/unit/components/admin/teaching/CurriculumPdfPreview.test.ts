import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import CurriculumPdfPreview, { resolvePdfWorkerUrl } from '@/pages/admin/teaching/curricula/CurriculumPdfPreview.vue'

describe('CurriculumPdfPreview', () => {
    it('normalizes a saved page-relative position', () => {
        const methods = (CurriculumPdfPreview as any).methods

        expect(methods.normalizePosition({ page: '4', offset: 0.35 })).toEqual({ page: 4, offset: 0.35 })
        expect(methods.normalizePosition({ page: 2, offset: 4 })).toEqual({ page: 2, offset: 1 })
        expect(methods.normalizePosition({ page: 0, offset: 0 })).toBeNull()
    })

    it('identifies the current page and relative offset from the scroll position', () => {
        const methods = (CurriculumPdfPreview as any).methods
        const pages = [
            { dataset: { pageNumber: '1' }, offsetHeight: 1000, offsetTop: 0 },
            { dataset: { pageNumber: '2' }, offsetHeight: 1000, offsetTop: 1012 },
            { dataset: { pageNumber: '3' }, offsetHeight: 1000, offsetTop: 2024 },
        ]
        const context = {
            $refs: {
                viewport: {
                    querySelectorAll: () => pages,
                    scrollTop: 1262,
                },
            },
            normalizePosition: methods.normalizePosition,
            pageScrollTop: methods.pageScrollTop,
            pageCount: 3,
        }

        expect(methods.currentPosition.call(context)).toEqual({ page: 2, offset: 0.25 })
    })

    it('restores the exact page-relative position', () => {
        const methods = (CurriculumPdfPreview as any).methods
        const selectedPage = { offsetHeight: 800, offsetTop: 1600 }
        const viewport = {
            querySelector: vi.fn().mockReturnValue(selectedPage),
            scrollTop: 0,
        }
        const context = {
            $refs: { viewport },
            currentPage: 1,
            initialPosition: null,
            isApplyingRestoredPosition: false,
            normalizePosition: methods.normalizePosition,
            pageScrollTop: methods.pageScrollTop,
            pageCount: 5,
            pendingPosition: { page: 3, offset: 0.5 },
            restoreReleaseTimer: null,
            scrollEmitTimer: null,
        }

        methods.restorePosition.call(context)

        expect(viewport.querySelector).toHaveBeenCalledWith('[data-page-number="3"]')
        expect(viewport.scrollTop).toBe(2000)
        expect(context.currentPage).toBe(3)
        expect(context.pendingPosition).toBeNull()
        expect(context.isApplyingRestoredPosition).toBe(true)
    })

    it('calculates page positions relative to the scrolling viewport', () => {
        const methods = (CurriculumPdfPreview as any).methods
        const viewport = {
            getBoundingClientRect: () => ({ top: 100 }),
            scrollTop: 1262,
        }
        const page = {
            getBoundingClientRect: () => ({ top: -150 }),
            offsetTop: 9999,
        }

        expect(methods.pageScrollTop(viewport, page)).toBe(1012)
    })

    it('ignores the initial resize measurement while the saved position is being restored', () => {
        const methods = (CurriculumPdfPreview as any).methods
        let resizeCallback: (entries: Array<{ contentRect: { width: number } }>) => void = () => {}
        const observe = vi.fn()

        vi.stubGlobal('ResizeObserver', class {
            constructor(callback: typeof resizeCallback) {
                resizeCallback = callback
            }

            observe = observe
        })

        const context = {
            $refs: { viewport: {} },
            lastViewportWidth: 0,
            resizeObserver: null,
            resizeTimer: null,
        }

        methods.observeViewportResize.call(context)
        resizeCallback([{ contentRect: { width: 640 } }])

        expect(observe).toHaveBeenCalledWith(context.$refs.viewport)
        expect(context.resizeTimer).toBeNull()

        vi.unstubAllGlobals()
    })

    it('loads PDF.js lazily and uses its matching worker bundle', () => {
        const source = readFileSync(resolve('resources/js/pages/admin/teaching/curricula/CurriculumPdfPreview.vue'), 'utf8')

        expect(source).toContain("import('pdfjs-dist/build/pdf.worker.min.mjs?url')")
        expect(source).not.toContain("import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url'")
        expect(source).toContain("const pdfjs = await import('pdfjs-dist')")
        expect(source).toContain('pdfjs.GlobalWorkerOptions.workerSrc = await resolvePdfWorkerUrl()')
        expect(source).toContain('disableFontFace: true')
        expect(source).toContain("this.$emit('position-change', position, this.documentId)")
        expect(source).not.toContain('scroll-behavior: smooth')
    })

    it('uses the native browser preview when canvas rendering fails', () => {
        const methods = (CurriculumPdfPreview as any).methods
        const error = new Error('Malformed embedded font')
        const cancelRenderTasks = vi.fn()
        const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})
        const context = {
            cancelRenderTasks,
            errorMessage: 'Previous error',
            isLoading: true,
            useNativePreview: false,
        }

        methods.activateNativePreview.call(context, error)

        expect(cancelRenderTasks).toHaveBeenCalledOnce()
        expect(consoleError).toHaveBeenCalledWith(
            'Curriculum PDF canvas rendering failed; using the native preview.',
            error,
        )
        expect(context.errorMessage).toBe('')
        expect(context.isLoading).toBe(false)
        expect(context.useNativePreview).toBe(true)

        consoleError.mockRestore()
    })

    it('creates a same-origin worker URL when Vite runs on a different port', async () => {
        const workerBlob = new Blob(['pdf-worker'])
        const fetchMock = vi.fn().mockResolvedValue({
            blob: vi.fn().mockResolvedValue(workerBlob),
            ok: true,
        })
        const createObjectUrl = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:http://localhost:8000/pdf-worker')
        vi.stubGlobal('fetch', fetchMock)

        const resolvedWorkerUrl = await resolvePdfWorkerUrl(
            'http://localhost:8000/admin/teaching/curricula',
            'http://localhost:5173/pdf.worker.min.mjs',
        )

        expect(fetchMock).toHaveBeenCalledOnce()
        expect(fetchMock).toHaveBeenCalledWith(expect.stringMatching(/^http:\/\/localhost:5173\//))
        expect(createObjectUrl).toHaveBeenCalledWith(workerBlob)
        expect(resolvedWorkerUrl).toBe('blob:http://localhost:8000/pdf-worker')

        vi.unstubAllGlobals()
        vi.restoreAllMocks()
    })
})
