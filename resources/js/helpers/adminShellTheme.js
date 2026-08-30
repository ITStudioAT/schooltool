const DEFAULT_ADMIN_SHELL_COLOR = 'primary'
const DARK_ADMIN_SHELL_TEXT_COLOR = '#10263A'
const LIGHT_ADMIN_SHELL_TEXT_COLOR = '#FFFFFF'
const SCHOOL_COLOR_PATTERN = /^#[0-9A-Fa-f]{6}$/

export function resolveAdminShellColor(school, user) {
    if (user?.use_school_color_for_admin_ui !== true) {
        return DEFAULT_ADMIN_SHELL_COLOR
    }

    const schoolColor = typeof school?.color === 'string' ? school.color.trim() : ''

    return SCHOOL_COLOR_PATTERN.test(schoolColor) ? schoolColor : DEFAULT_ADMIN_SHELL_COLOR
}

export function resolveAdminShellTextColor(shellColor) {
    if (!SCHOOL_COLOR_PATTERN.test(shellColor)) {
        return null
    }

    const backgroundLuminance = relativeLuminance(shellColor)
    const darkTextContrast = contrastRatio(backgroundLuminance, relativeLuminance(DARK_ADMIN_SHELL_TEXT_COLOR))
    const lightTextContrast = contrastRatio(backgroundLuminance, relativeLuminance(LIGHT_ADMIN_SHELL_TEXT_COLOR))

    return darkTextContrast >= lightTextContrast ? DARK_ADMIN_SHELL_TEXT_COLOR : LIGHT_ADMIN_SHELL_TEXT_COLOR
}

function relativeLuminance(color) {
    const channels = [color.slice(1, 3), color.slice(3, 5), color.slice(5, 7)]
        .map((channel) => Number.parseInt(channel, 16) / 255)
        .map((channel) => channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4)

    return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722
}

function contrastRatio(firstLuminance, secondLuminance) {
    const lighter = Math.max(firstLuminance, secondLuminance)
    const darker = Math.min(firstLuminance, secondLuminance)

    return (lighter + 0.05) / (darker + 0.05)
}
