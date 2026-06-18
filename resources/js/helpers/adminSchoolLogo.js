export function resolveSelectedSchoolLogoSrc(logo) {
    if (!logo) return null

    const rawLogo = String(logo).trim().replace(/\\/g, '/')
    if (!rawLogo) return null

    if (rawLogo.startsWith('http://') || rawLogo.startsWith('https://') || rawLogo.startsWith('/storage/')) {
        return rawLogo
    }

    const normalizedLogo = rawLogo.replace(/^\/+/, '')
    if (!normalizedLogo) return null

    if (normalizedLogo.startsWith('storage/')) {
        return `/${normalizedLogo}`
    }

    if (normalizedLogo.startsWith('images/')) {
        return `/storage/${normalizedLogo}`
    }

    if (normalizedLogo.startsWith('logos/')) {
        return `/storage/images/${normalizedLogo}`
    }

    return `/storage/images/${normalizedLogo}`
}
