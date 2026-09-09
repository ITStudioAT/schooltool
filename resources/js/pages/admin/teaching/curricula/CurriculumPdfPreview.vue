<template>
    <div class="curriculum-pdf-preview">
        <div v-if="!useNativePreview" class="curriculum-pdf-preview__toolbar">
            <button
                type="button"
                class="curriculum-pdf-preview__page-button"
                :disabled="isLoading || currentPage <= 1"
                aria-label="Vorherige Seite"
                @click="goToPage(currentPage - 1)">
                <v-icon icon="mdi-chevron-left" size="20" />
            </button>
            <span class="curriculum-pdf-preview__page-status" aria-live="polite">
                Seite {{ currentPage }} / {{ pageCount || '–' }}
            </span>
            <button
                type="button"
                class="curriculum-pdf-preview__page-button"
                :disabled="isLoading || currentPage >= pageCount"
                aria-label="Nächste Seite"
                @click="goToPage(currentPage + 1)">
                <v-icon icon="mdi-chevron-right" size="20" />
            </button>
        </div>

        <iframe
            v-if="useNativePreview"
            :src="src"
            class="curriculum-pdf-preview__native-preview"
            title="PDF-Vorschau" />
        <div v-show="!useNativePreview" ref="viewport" class="curriculum-pdf-preview__viewport" @scroll.passive="handleScroll">
            <div v-if="errorMessage" class="curriculum-pdf-preview__state curriculum-pdf-preview__state--error">
                <span>{{ errorMessage }}</span>
                <a :href="src" target="_blank" rel="noopener">PDF direkt öffnen</a>
            </div>
            <div v-else-if="isLoading && !pageCount" class="curriculum-pdf-preview__state" role="status">
                <span class="curriculum-pdf-preview__spinner" aria-hidden="true" />
                PDF wird geladen …
            </div>
            <div v-if="!errorMessage && pageCount" class="curriculum-pdf-preview__pages">
                <div
                    v-for="pageNumber in pageNumbers"
                    :key="pageNumber"
                    class="curriculum-pdf-preview__page"
                    :data-page-number="pageNumber">
                    <canvas :data-pdf-canvas="pageNumber" />
                    <span class="curriculum-pdf-preview__page-number">{{ pageNumber }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { markRaw } from 'vue'

let sameOriginPdfWorkerUrlPromise = null
let pdfWorkerUrlPromise = null

async function loadPdfWorkerUrl() {
    pdfWorkerUrlPromise ??= import('pdfjs-dist/build/pdf.worker.min.mjs?url').then((module) => module.default)

    return pdfWorkerUrlPromise
}

export async function resolvePdfWorkerUrl(baseUrl = window.location.href, sourceUrl = null) {
    const applicationUrl = new URL(baseUrl)
    const workerUrl = new URL(sourceUrl || (await loadPdfWorkerUrl()), applicationUrl)

    if (workerUrl.origin === applicationUrl.origin) return workerUrl.href

    sameOriginPdfWorkerUrlPromise ??= fetch(workerUrl.href).then(async (response) => {
        if (!response.ok) {
            throw new Error(`PDF worker could not be loaded (${response.status}).`)
        }

        return URL.createObjectURL(await response.blob())
    })

    return sameOriginPdfWorkerUrlPromise
}

export default {
    name: 'CurriculumPdfPreview',
    props: {
        documentId: {
            type: [Number, String],
            required: true,
        },
        initialPosition: {
            type: Object,
            default: null,
        },
        src: {
            type: String,
            required: true,
        },
    },
    emits: ['position-change'],

    data() {
        return {
            currentPage: 1,
            errorMessage: '',
            isApplyingRestoredPosition: false,
            isLoading: true,
            lastViewportWidth: 0,
            loadGeneration: 0,
            loadingTask: null,
            pageCount: 0,
            pdfDocument: null,
            pendingPosition: null,
            renderGeneration: 0,
            renderTasks: [],
            resizeObserver: null,
            resizeTimer: null,
            restoreReleaseTimer: null,
            scrollEmitTimer: null,
            useNativePreview: false,
        }
    },

    computed: {
        pageNumbers() {
            return Array.from({ length: this.pageCount }, (_, index) => index + 1)
        },
    },

    watch: {
        src() {
            this.loadDocument()
        },
        initialPosition: {
            deep: true,
            handler(position) {
                this.pendingPosition = this.normalizePosition(position)
                this.restorePosition()
            },
        },
    },

    mounted() {
        this.observeViewportResize()
        this.loadDocument()
    },

    beforeUnmount() {
        this.emitCurrentPosition()
        this.destroyDocument()
        this.resizeObserver?.disconnect()
        clearTimeout(this.resizeTimer)
        clearTimeout(this.restoreReleaseTimer)
        clearTimeout(this.scrollEmitTimer)
    },

    methods: {
        normalizePosition(position) {
            const page = Number.parseInt(position?.page, 10)
            const offset = Number(position?.offset)

            if (!Number.isInteger(page) || page < 1) return null

            return {
                offset: Number.isFinite(offset) ? Math.min(1, Math.max(0, offset)) : 0,
                page,
            }
        },
        observeViewportResize() {
            if (typeof ResizeObserver === 'undefined') return

            this.resizeObserver = new ResizeObserver((entries) => {
                const nextWidth = Math.round(entries[0]?.contentRect?.width || 0)
                if (!nextWidth || !this.lastViewportWidth || Math.abs(nextWidth - this.lastViewportWidth) < 2) return

                clearTimeout(this.resizeTimer)
                this.resizeTimer = setTimeout(() => {
                    if (!this.pdfDocument) return

                    this.pendingPosition = this.pendingPosition || this.currentPosition()
                    this.renderPages().catch((error) => {
                        this.activateNativePreview(error)
                    })
                }, 180)
            })
            this.resizeObserver.observe(this.$refs.viewport)
        },
        async loadDocument() {
            const generation = ++this.loadGeneration
            await this.destroyDocument({ preserveLoadGeneration: true })

            this.currentPage = 1
            this.errorMessage = ''
            this.isLoading = true
            this.pageCount = 0
            this.pendingPosition = this.normalizePosition(this.initialPosition)
            this.useNativePreview = false

            try {
                const pdfjs = await import('pdfjs-dist')
                pdfjs.GlobalWorkerOptions.workerSrc = await resolvePdfWorkerUrl()

                const loadingTask = pdfjs.getDocument({
                    disableFontFace: true,
                    isEvalSupported: false,
                    url: this.src,
                    withCredentials: true,
                })
                this.loadingTask = markRaw(loadingTask)

                const pdfDocument = await loadingTask.promise
                if (generation !== this.loadGeneration) {
                    await pdfDocument.destroy()
                    return
                }

                this.pdfDocument = markRaw(pdfDocument)
                this.pageCount = pdfDocument.numPages
                await this.$nextTick()
                await this.renderPages()
            } catch (error) {
                if (generation === this.loadGeneration) {
                    if (this.pageCount) {
                        this.activateNativePreview(error)
                    } else {
                        console.error('Curriculum PDF preview failed.', error)
                        this.errorMessage = 'Die PDF-Vorschau konnte nicht geladen werden.'
                    }
                }
            } finally {
                if (generation === this.loadGeneration) {
                    this.isLoading = false
                }
            }
        },
        async destroyDocument({ preserveLoadGeneration = false } = {}) {
            if (!preserveLoadGeneration) this.loadGeneration += 1
            this.renderGeneration += 1

            this.cancelRenderTasks()

            const loadingTask = this.loadingTask
            const pdfDocument = this.pdfDocument
            this.loadingTask = null
            this.pdfDocument = null

            try {
                await loadingTask?.destroy?.()
            } catch {
                // The loading task may already be settled or cancelled.
            }

            try {
                await pdfDocument?.destroy?.()
            } catch {
                // The PDF document may already be destroyed by its loading task.
            }
        },
        cancelRenderTasks() {
            this.renderTasks.forEach((renderTask) => {
                try {
                    renderTask.cancel()
                } catch {
                    // Completed render tasks cannot always be cancelled again.
                }
            })
            this.renderTasks = []
        },
        activateNativePreview(error) {
            console.error('Curriculum PDF canvas rendering failed; using the native preview.', error)
            this.cancelRenderTasks()
            this.errorMessage = ''
            this.isLoading = false
            this.useNativePreview = true
        },
        async renderPages() {
            if (!this.pdfDocument || !this.$refs.viewport) return

            const generation = ++this.renderGeneration
            const pdfDocument = this.pdfDocument
            const viewportWidth = Math.max(this.$refs.viewport.clientWidth - 24, 240)
            this.lastViewportWidth = Math.round(this.$refs.viewport.clientWidth)
            this.cancelRenderTasks()

            for (let pageNumber = 1; pageNumber <= pdfDocument.numPages; pageNumber += 1) {
                if (generation !== this.renderGeneration) return

                const canvas = this.$refs.viewport.querySelector(`[data-pdf-canvas="${pageNumber}"]`)
                if (!canvas) continue

                const page = await pdfDocument.getPage(pageNumber)
                const naturalViewport = page.getViewport({ scale: 1 })
                const viewport = page.getViewport({ scale: viewportWidth / naturalViewport.width })
                const outputScale = Math.min(window.devicePixelRatio || 1, 2)
                const canvasContext = canvas.getContext('2d')
                if (!canvasContext) continue

                canvas.width = Math.floor(viewport.width * outputScale)
                canvas.height = Math.floor(viewport.height * outputScale)
                canvas.style.width = `${Math.floor(viewport.width)}px`
                canvas.style.height = `${Math.floor(viewport.height)}px`

                const renderTask = page.render({
                    canvasContext,
                    transform: outputScale === 1 ? null : [outputScale, 0, 0, outputScale, 0, 0],
                    viewport,
                })
                this.renderTasks.push(markRaw(renderTask))

                try {
                    await renderTask.promise
                } catch (error) {
                    if (error?.name !== 'RenderingCancelledException') throw error
                }
            }

            if (generation !== this.renderGeneration) return

            this.isLoading = false
            await this.$nextTick()
            this.restorePosition()
        },
        currentPosition() {
            const viewport = this.$refs.viewport
            if (!viewport || !this.pageCount) return null

            const pages = [...viewport.querySelectorAll('[data-page-number]')]
            const scrollTop = viewport.scrollTop
            const activePage = [...pages]
                .reverse()
                .find((page) => this.pageScrollTop(viewport, page) <= scrollTop + 8) || pages[0]
            if (!activePage) return null

            const page = Number.parseInt(activePage.dataset.pageNumber, 10)
            const offset = (scrollTop - this.pageScrollTop(viewport, activePage)) / Math.max(activePage.offsetHeight, 1)

            return this.normalizePosition({ page, offset })
        },
        pageScrollTop(viewport, page) {
            if (typeof viewport.getBoundingClientRect !== 'function' || typeof page.getBoundingClientRect !== 'function') {
                return page.offsetTop
            }

            const viewportRect = viewport.getBoundingClientRect()
            const pageRect = page.getBoundingClientRect()

            return pageRect.top - viewportRect.top + viewport.scrollTop
        },
        restorePosition() {
            const viewport = this.$refs.viewport
            const position = this.pendingPosition || this.normalizePosition(this.initialPosition)
            if (!viewport || !position || !this.pageCount) return

            const pageNumber = Math.min(this.pageCount, position.page)
            const page = viewport.querySelector(`[data-page-number="${pageNumber}"]`)
            if (!page || page.offsetHeight <= 0) return

            clearTimeout(this.scrollEmitTimer)
            clearTimeout(this.restoreReleaseTimer)
            this.isApplyingRestoredPosition = true
            viewport.scrollTop = this.pageScrollTop(viewport, page) + (page.offsetHeight * position.offset)
            this.currentPage = pageNumber
            this.pendingPosition = null

            this.restoreReleaseTimer = setTimeout(() => {
                this.isApplyingRestoredPosition = false
            }, 120)
        },
        handleScroll() {
            const position = this.currentPosition()
            if (position) this.currentPage = position.page
            if (this.isApplyingRestoredPosition) return

            clearTimeout(this.scrollEmitTimer)
            this.scrollEmitTimer = setTimeout(() => this.emitCurrentPosition(), 150)
        },
        emitCurrentPosition() {
            const position = this.currentPosition()
            if (position) this.$emit('position-change', position, this.documentId)
        },
        goToPage(pageNumber) {
            const normalizedPage = Math.min(this.pageCount, Math.max(1, Number.parseInt(pageNumber, 10)))
            this.pendingPosition = { page: normalizedPage, offset: 0 }
            this.restorePosition()
            this.emitCurrentPosition()
        },
    },
}
</script>

<style scoped>
.curriculum-pdf-preview {
    display: flex;
    flex-direction: column;
    min-height: 240px;
    background: #e2e8f0;
}

.curriculum-pdf-preview__toolbar {
    position: sticky;
    z-index: 2;
    top: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 40px;
    padding: 4px 8px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.14);
    background: rgba(248, 250, 252, 0.96);
}

.curriculum-pdf-preview__page-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 1px solid rgba(99, 102, 241, 0.24);
    border-radius: 999px;
    background: #fff;
    color: #3730a3;
    cursor: pointer;
}

.curriculum-pdf-preview__page-button:hover:not(:disabled),
.curriculum-pdf-preview__page-button:focus-visible {
    border-color: rgba(79, 70, 229, 0.65);
    outline: 3px solid rgba(99, 102, 241, 0.14);
}

.curriculum-pdf-preview__page-button:disabled {
    cursor: default;
    opacity: 0.38;
}

.curriculum-pdf-preview__page-status {
    min-width: 92px;
    color: #334155;
    font-size: 0.76rem;
    font-weight: 700;
    text-align: center;
}

.curriculum-pdf-preview__viewport {
    max-height: min(70vh, 760px);
    min-height: 240px;
    overflow: auto;
    overscroll-behavior: contain;
    touch-action: pan-y pinch-zoom;
}

.curriculum-pdf-preview__native-preview {
    display: block;
    width: 100%;
    height: min(70vh, 760px);
    min-height: 240px;
    border: 0;
    background: #fff;
}

.curriculum-pdf-preview__pages {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 12px;
}

.curriculum-pdf-preview__page {
    position: relative;
    max-width: 100%;
    overflow: hidden;
    border-radius: 3px;
    background: #fff;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.2);
}

.curriculum-pdf-preview__page canvas {
    display: block;
    max-width: 100%;
}

.curriculum-pdf-preview__page-number {
    position: absolute;
    right: 6px;
    bottom: 6px;
    padding: 2px 6px;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.72);
    color: #fff;
    font-size: 0.65rem;
}

.curriculum-pdf-preview__state {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    min-height: 240px;
    padding: 24px;
    color: #475569;
    font-size: 0.8rem;
    text-align: center;
}

.curriculum-pdf-preview__state--error {
    flex-direction: column;
    color: #991b1b;
}

.curriculum-pdf-preview__state--error a {
    color: #3730a3;
    font-weight: 700;
}

.curriculum-pdf-preview__spinner {
    width: 22px;
    height: 22px;
    border: 3px solid rgba(99, 102, 241, 0.18);
    border-top-color: #4f46e5;
    border-radius: 999px;
    animation: curriculum-pdf-preview-spin 800ms linear infinite;
}

@keyframes curriculum-pdf-preview-spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 600px) {
    .curriculum-pdf-preview__page-button {
        width: 44px;
        height: 44px;
    }

    .curriculum-pdf-preview__toolbar {
        min-height: 52px;
    }

    .curriculum-pdf-preview__viewport {
        max-height: 68vh;
    }
}
</style>
