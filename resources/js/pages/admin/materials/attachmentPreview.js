const activePreviewMimeTypes = new Set([
    'application/atom+xml',
    'application/rss+xml',
    'application/xhtml+xml',
    'application/xml',
    'image/svg+xml',
    'text/html',
    'text/xml',
])

const activePreviewExtensions = new Set(['htm', 'html', 'mht', 'mhtml', 'svg', 'xhtml', 'xml'])

const directPreviewMimeTypes = new Set([
    'application/json',
    'application/msword',
    'application/pdf',
    'application/rtf',
    'application/vnd.ms-excel',
    'application/vnd.ms-powerpoint',
    'application/vnd.oasis.opendocument.presentation',
    'application/vnd.oasis.opendocument.spreadsheet',
    'application/vnd.oasis.opendocument.text',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'audio/aac',
    'audio/flac',
    'audio/mpeg',
    'audio/mp4',
    'audio/ogg',
    'audio/wav',
    'audio/webm',
    'image/avif',
    'image/bmp',
    'image/gif',
    'image/heic',
    'image/heif',
    'image/jpeg',
    'image/png',
    'image/tiff',
    'image/webp',
    'text/csv',
    'text/markdown',
    'text/plain',
    'text/rtf',
    'text/tab-separated-values',
    'video/mp4',
    'video/ogg',
    'video/quicktime',
    'video/webm',
])

const directPreviewMimeTypesByExtension = {
    aac: 'audio/aac',
    avif: 'image/avif',
    bmp: 'image/bmp',
    csv: 'text/csv',
    doc: 'application/msword',
    docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    flac: 'audio/flac',
    gif: 'image/gif',
    heic: 'image/heic',
    heif: 'image/heif',
    jpeg: 'image/jpeg',
    jpg: 'image/jpeg',
    json: 'application/json',
    md: 'text/markdown',
    mov: 'video/quicktime',
    mp3: 'audio/mpeg',
    mp4: 'video/mp4',
    odp: 'application/vnd.oasis.opendocument.presentation',
    ods: 'application/vnd.oasis.opendocument.spreadsheet',
    odt: 'application/vnd.oasis.opendocument.text',
    oga: 'audio/ogg',
    ogg: 'audio/ogg',
    ogv: 'video/ogg',
    pdf: 'application/pdf',
    png: 'image/png',
    ppt: 'application/vnd.ms-powerpoint',
    pptx: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    rtf: 'application/rtf',
    tab: 'text/tab-separated-values',
    tif: 'image/tiff',
    tiff: 'image/tiff',
    tsv: 'text/tab-separated-values',
    txt: 'text/plain',
    wav: 'audio/wav',
    webm: 'video/webm',
    webp: 'image/webp',
    xls: 'application/vnd.ms-excel',
    xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
}

const activePreviewMimeTypesByExtension = {
    htm: 'text/html',
    html: 'text/html',
    mht: 'text/html',
    mhtml: 'text/html',
    svg: 'image/svg+xml',
    xhtml: 'application/xhtml+xml',
    xml: 'application/xml',
}

const previewObjectUrlLifetimeMilliseconds = 120000

export class UnsupportedAttachmentPreviewError extends Error {
    constructor() {
        super('This attachment type cannot be previewed safely.')
        this.name = 'UnsupportedAttachmentPreviewError'
    }
}

function normalizeMimeType(value) {
    return String(value || '')
        .split(';', 1)[0]
        .trim()
        .toLowerCase()
}

function attachmentExtension(attachment) {
    const fileName = String(attachment?.name || attachment?.file_path || '')
        .trim()
        .toLowerCase()
    const extensionStart = fileName.lastIndexOf('.')

    return extensionStart >= 0 ? fileName.slice(extensionStart + 1) : ''
}

function mimeTypeCandidates({ blob, responseContentType, attachment }) {
    return [
        normalizeMimeType(responseContentType),
        normalizeMimeType(blob?.type),
        normalizeMimeType(attachment?.mime_type),
    ].filter((mimeType, index, mimeTypes) => mimeType !== '' && mimeTypes.indexOf(mimeType) === index)
}

function escapeHtml(value) {
    return String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;')
}

function buildSandboxDocument(contentObjectUrl, attachmentName) {
    const safeContentObjectUrl = escapeHtml(contentObjectUrl)
    const safeAttachmentName = escapeHtml(attachmentName || 'Dateivorschau')

    return `<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="referrer" content="no-referrer">
    <meta http-equiv="Content-Security-Policy" content="default-src 'none'; frame-src blob:; style-src 'unsafe-inline'">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>${safeAttachmentName}</title>
    <style>
        html, body, iframe { width: 100%; height: 100%; margin: 0; border: 0; background: #fff; }
    </style>
</head>
<body>
    <iframe
        title="${safeAttachmentName}"
        src="${safeContentObjectUrl}"
        sandbox="allow-downloads"
        referrerpolicy="no-referrer"></iframe>
</body>
</html>`
}

function openObjectUrlInNewTab(objectUrl) {
    const link = document.createElement('a')
    link.href = objectUrl
    link.target = '_blank'
    link.rel = 'noopener noreferrer'
    link.referrerPolicy = 'no-referrer'
    link.hidden = true
    document.body.appendChild(link)
    link.click()
    link.remove()
}

function scheduleObjectUrlRevocation(objectUrls) {
    window.setTimeout(() => {
        objectUrls.forEach((objectUrl) => URL.revokeObjectURL(objectUrl))
    }, previewObjectUrlLifetimeMilliseconds)
}

function resolvePreviewType({ blob, responseContentType, attachment }) {
    const mimeTypes = mimeTypeCandidates({ blob, responseContentType, attachment })
    const extension = attachmentExtension(attachment)
    const activeMimeType = mimeTypes.find((mimeType) => activePreviewMimeTypes.has(mimeType))

    if (activeMimeType || activePreviewExtensions.has(extension)) {
        return {
            mode: 'sandboxed',
            mimeType: activeMimeType || activePreviewMimeTypesByExtension[extension] || 'text/html',
        }
    }

    const directMimeType = mimeTypes.find((mimeType) => directPreviewMimeTypes.has(mimeType))
    const extensionMimeType = directPreviewMimeTypesByExtension[extension]

    if (directMimeType || extensionMimeType) {
        return {
            mode: 'direct',
            mimeType: directMimeType || extensionMimeType,
        }
    }

    throw new UnsupportedAttachmentPreviewError()
}

export function openAttachmentPreview({ blob, responseContentType = '', attachment = {} }) {
    const previewType = resolvePreviewType({ blob, responseContentType, attachment })
    const previewBlob = new Blob([blob], { type: previewType.mimeType })
    const previewObjectUrl = URL.createObjectURL(previewBlob)

    if (previewType.mode === 'direct') {
        openObjectUrlInNewTab(previewObjectUrl)
        scheduleObjectUrlRevocation([previewObjectUrl])

        return previewType
    }

    // Server-side sanitization remains mandatory. The iframe is a second boundary that
    // prevents any missed active content from receiving scripts or the application origin.
    const sandboxDocument = buildSandboxDocument(previewObjectUrl, attachment?.name)
    const sandboxBlob = new Blob([sandboxDocument], { type: 'text/html' })
    const sandboxObjectUrl = URL.createObjectURL(sandboxBlob)

    openObjectUrlInNewTab(sandboxObjectUrl)
    scheduleObjectUrlRevocation([previewObjectUrl, sandboxObjectUrl])

    return previewType
}
