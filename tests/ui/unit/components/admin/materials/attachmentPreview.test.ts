import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
    openAttachmentPreview,
    UnsupportedAttachmentPreviewError,
} from '@/pages/admin/materials/attachmentPreview'

describe('attachment preview security', () => {
    const createdBlobs: Blob[] = []
    const openedLinks: Array<{
        href: string
        target: string
        rel: string
        referrerPolicy: string
    }> = []

    beforeEach(() => {
        vi.useFakeTimers()
        createdBlobs.length = 0
        openedLinks.length = 0

        vi.stubGlobal('URL', {
            createObjectURL: vi.fn((blob: Blob) => {
                createdBlobs.push(blob)

                return `blob:preview-${createdBlobs.length}`
            }),
            revokeObjectURL: vi.fn(),
        })

        vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function () {
            openedLinks.push({
                href: this.href,
                target: this.target,
                rel: this.rel,
                referrerPolicy: this.referrerPolicy,
            })
        })
    })

    afterEach(() => {
        vi.useRealTimers()
        vi.unstubAllGlobals()
        vi.restoreAllMocks()
    })

    it('opens allowlisted passive files directly without an opener or referrer', () => {
        const result = openAttachmentPreview({
            blob: new Blob(['%PDF-1.7'], { type: 'application/octet-stream' }),
            responseContentType: 'application/pdf; charset=binary',
            attachment: {
                name: 'Arbeitsblatt.pdf',
                mime_type: 'application/pdf',
            },
        })

        expect(result).toEqual({
            mode: 'direct',
            mimeType: 'application/pdf',
        })
        expect(createdBlobs).toHaveLength(1)
        expect(createdBlobs[0]?.type).toBe('application/pdf')
        expect(openedLinks).toEqual([
            {
                href: 'blob:preview-1',
                target: '_blank',
                rel: 'noopener noreferrer',
                referrerPolicy: 'no-referrer',
            },
        ])

        vi.runAllTimers()
        expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:preview-1')
    })

    it('isolates active HTML inside a scriptless opaque-origin sandbox', async () => {
        const result = openAttachmentPreview({
            blob: new Blob(['<script>window.opener.location = "/stolen"</script>'], {
                type: 'text/html',
            }),
            responseContentType: 'text/html; charset=UTF-8',
            attachment: {
                name: '"><img src=x onerror=alert(1)>.html',
                mime_type: 'text/html',
            },
        })

        expect(result).toEqual({
            mode: 'sandboxed',
            mimeType: 'text/html',
        })
        expect(createdBlobs).toHaveLength(2)
        expect(createdBlobs[0]?.type).toBe('text/html')
        expect(createdBlobs[1]?.type).toBe('text/html')
        expect(openedLinks).toEqual([
            {
                href: 'blob:preview-2',
                target: '_blank',
                rel: 'noopener noreferrer',
                referrerPolicy: 'no-referrer',
            },
        ])

        const sandboxDocument = await createdBlobs[1].text()
        expect(sandboxDocument).toContain('src="blob:preview-1"')
        expect(sandboxDocument).toContain('sandbox="allow-downloads"')
        expect(sandboxDocument).toContain('referrerpolicy="no-referrer"')
        expect(sandboxDocument).toContain("default-src 'none'; frame-src blob:")
        expect(sandboxDocument).not.toContain('allow-scripts')
        expect(sandboxDocument).not.toContain('allow-same-origin')
        expect(sandboxDocument).not.toContain('<img src=x onerror=alert(1)>')

        vi.runAllTimers()
        expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:preview-1')
        expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:preview-2')
    })

    it('sandboxes an active extension even when the server reports a passive MIME type', () => {
        const result = openAttachmentPreview({
            blob: new Blob(['<svg onload="alert(1)"></svg>'], { type: 'image/png' }),
            responseContentType: 'image/png',
            attachment: {
                name: 'diagram.svg',
                mime_type: 'image/png',
            },
        })

        expect(result).toEqual({
            mode: 'sandboxed',
            mimeType: 'image/svg+xml',
        })
        expect(openedLinks[0]?.href).toBe('blob:preview-2')
    })

    it('refuses unknown content instead of navigating a browser tab to it', () => {
        expect(() =>
            openAttachmentPreview({
                blob: new Blob(['MZ'], { type: 'application/octet-stream' }),
                responseContentType: 'application/octet-stream',
                attachment: {
                    name: 'programm.exe',
                    mime_type: 'application/octet-stream',
                },
            }),
        ).toThrow(UnsupportedAttachmentPreviewError)

        expect(createdBlobs).toHaveLength(0)
        expect(openedLinks).toHaveLength(0)
    })
})
